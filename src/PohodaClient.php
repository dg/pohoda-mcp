<?php declare(strict_types=1);

namespace DG\Pohoda;

use function in_array, is_string;


/**
 * HTTP client for Pohoda mServer API.
 */
class PohodaClient
{
	/********************* Agenda configuration ****************d*g**/


	private const AgendaNamespaces = [
		'stock' => ['listPrefix' => 'lStk', 'listXsd' => 'list_stock.xsd', 'listRequest' => 'listStockRequest', 'requestTag' => 'requestStock'],
		'invoice' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listInvoiceRequest', 'requestTag' => 'requestInvoice'],
		'addressbook' => ['listPrefix' => 'lAdb', 'listXsd' => 'list_addBook.xsd', 'listRequest' => 'listAddressBookRequest', 'requestTag' => 'requestAddressBook', 'versionAttr' => 'addressBookVersion'],
		'order' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listOrderRequest', 'requestTag' => 'requestOrder'],
		'voucher' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listVoucherRequest', 'requestTag' => 'requestVoucher'],
		'bank' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listBankRequest', 'requestTag' => 'requestBank'],
		'contract' => ['listPrefix' => 'lCon', 'listXsd' => 'list_contract.xsd', 'listRequest' => 'listContractRequest', 'requestTag' => 'requestContract'],
		'intDoc' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listIntDocRequest', 'requestTag' => 'requestIntDoc'],
		'offer' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listOfferRequest', 'requestTag' => 'requestOffer'],
		'enquiry' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listEnquiryRequest', 'requestTag' => 'requestEnquiry'],
		'vydejka' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listVydejkaRequest', 'requestTag' => 'requestVydejka'],
		'prijemka' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listPrijemkaRequest', 'requestTag' => 'requestPrijemka'],
		'prodejka' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listProdejkaRequest', 'requestTag' => 'requestProdejka'],
		'prevodka' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listPrevodkaRequest', 'requestTag' => 'requestPrevodka'],
		'vyroba' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listVyrobaRequest', 'requestTag' => 'requestVyroba'],
		'accountancy' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listAccountancyRequest', 'requestTag' => 'requestAccountancy'],
		'store' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listStoreRequest', 'requestTag' => 'requestStore'],
		'bankAccount' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listBankAccountRequest', 'requestTag' => 'requestBankAccount', 'versionAttr' => 'bankAccountVersion'],
		'cashRegister' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listCashRegisterRequest', 'requestTag' => 'requestCashRegister', 'versionAttr' => 'cashRegisterVersion'],
		'numericalSeries' => ['listPrefix' => 'lst', 'listXsd' => 'list.xsd', 'listRequest' => 'listNumericalSeriesRequest', 'requestTag' => 'requestNumericalSeries', 'versionAttr' => 'numericalSeriesVersion'],
		'centre' => ['listPrefix' => 'lCen', 'listXsd' => 'list_centre.xsd', 'listRequest' => 'listCentreRequest', 'requestTag' => 'requestCentre'],
		'activity' => ['listPrefix' => 'lAcv', 'listXsd' => 'list_activity.xsd', 'listRequest' => 'listActivityRequest', 'requestTag' => 'requestActivity'],
	];
	private string $authHeader;
	private XmlBuilder $xml;


	public function __construct(
		private string $url,
		string $ico,
		string $username,
		string $password,
		string $application = 'MCP Server',
	) {
		$this->url = rtrim($url, '/');
		$this->authHeader = 'Basic ' . base64_encode($username . ':' . $password);
		$this->xml = new XmlBuilder($ico, $application);
	}


	/********************* High-level API ****************d*g**/


	/**
	 * Check mServer status.
	 * @return array{status?: string, server?: string, processing?: string, message?: string, company?: string, databaseName?: string, year?: string}
	 */
	public function getStatus(bool $companyDetail = false): array
	{
		$url = $this->url . '/status' . ($companyDetail ? '?companyDetail' : '');
		$headers = $companyDetail ? ['STW-Authorization: ' . $this->authHeader] : [];
		$response = $this->httpGet($url, $headers);

		$doc = @simplexml_load_string($response);
		if ($doc === false) {
			return ['message' => trim($response)];
		}

		$result = [
			'status' => (string) $doc->status,
			'server' => (string) $doc->server,
			'processing' => (string) $doc->processing,
		];
		if (isset($doc->companyDetail)) {
			$result['company'] = (string) $doc->companyDetail->company;
			$result['databaseName'] = (string) $doc->companyDetail->databaseName;
			$result['year'] = (string) $doc->companyDetail->year;
		}
		return $result;
	}


	/**
	 * List records from a given agenda.
	 * @param array<string, mixed> $filter
	 */
	public function listRecords(string $agenda, array $filter = [], string $subType = ''): Response
	{
		$ns = self::AgendaNamespaces[$agenda]
			?? throw new \InvalidArgumentException("Unknown agenda: $agenda. Supported: " . implode(', ', array_keys(self::AgendaNamespaces)));

		$versionAttr = $ns['versionAttr'] ?? ($agenda . 'Version');
		$requestTag = $ns['listPrefix'] . ':' . $ns['listRequest'];
		$innerTag = $ns['listPrefix'] . ':' . $ns['requestTag'];

		$filterData = self::buildFilterData($filter);
		$inner = [$innerTag => $filterData ? ['ftr:filter' => $filterData] : []];

		return $this->send($requestTag, '2.0', $inner, 'List ' . $agenda, [
			$versionAttr => '2.0',
			...($subType !== '' ? [$agenda . 'Type' => $subType] : []),
		]);
	}


	/**
	 * @param array<string, mixed> $header  Keys: type, date, partnerName, partnerIco, etc.
	 * @param list<array<string, mixed>> $items  Each: text, quantity, unit, unitPrice, vatRate, stockCode
	 */
	public function createInvoice(array $header, array $items = []): Response
	{
		$type = match ($header['type'] ?? '') {
			'receivedInvoice' => 'receivedInvoice',
			default => 'issuedInvoice',
		};
		$currency = $header['currency'] ?? '';

		$invoiceHeader = array_filter([
			'inv:invoiceType' => $type,
			'inv:symVar' => $header['symVar'] ?? null,
			'inv:date' => $header['date'] ?? null,
			'inv:dateTax' => $header['dateTax'] ?? null,
			'inv:dateAccounting' => $header['dateAccounting'] ?? null,
			'inv:dateDue' => $header['dateDue'] ?? null,
			'inv:accounting' => self::ids($header['accounting'] ?? ''),
			'inv:classificationVAT' => ($v = $header['classificationVAT'] ?? '') !== '' ? ['typ:classificationVATType' => $v] : null,
			'inv:text' => $header['text'] ?? null,
			'inv:partnerIdentity' => self::buildPartnerIdentity($header),
			'inv:paymentType' => self::buildPaymentType($header['paymentType'] ?? ''),
			'inv:account' => self::ids($header['accountIds'] ?? ''),
			'inv:note' => $header['note'] ?? null,
			'inv:intNote' => $header['intNote'] ?? null,
			'inv:centre' => self::ids($header['centre'] ?? ''),
			'inv:activity' => self::ids($header['activity'] ?? ''),
			'inv:contract' => self::ids($header['contract'] ?? ''),
		], fn($v) => $v !== null && $v !== '' && $v !== []);

		$invoiceItems = [];
		foreach ($items as $item) {
			$priceElement = $currency
				? ['inv:foreignCurrency' => ['typ:unitPrice' => (float) ($item['unitPrice'] ?? 0)]]
				: ['inv:homeCurrency' => ['typ:unitPrice' => (float) ($item['unitPrice'] ?? 0)]];

			$invoiceItems[] = array_filter([
				'inv:text' => $item['text'] ?? '',
				'inv:quantity' => (float) ($item['quantity'] ?? 1),
				'inv:unit' => $item['unit'] ?? null,
				'inv:rateVAT' => $item['vatRate'] ?? 'high',
				...$priceElement,
				'inv:stockItem' => ($code = $item['stockCode'] ?? '') !== ''
					? ['typ:stockItem' => ['typ:ids' => $code]]
					: null,
			], fn($v) => $v !== null && $v !== '');
		}

		$data = ['inv:invoiceHeader' => $invoiceHeader];
		if ($invoiceItems) {
			$data['inv:invoiceDetail'] = array_map(fn($item) => ['inv:invoiceItem' => $item], $invoiceItems);
		}
		if ($currency) {
			$data['inv:invoiceSummary'] = [
				'inv:foreignCurrency' => [
					'typ:currency' => ['typ:ids' => $currency],
					'typ:rate' => (float) ($header['currencyRate'] ?? 0) ?: 1,
					'typ:amount' => 1,
				],
			];
		}

		return $this->send('inv:invoice', '2.0', $data, 'Create invoice');
	}


	/**
	 * @param array<string, string> $data  Keys: company, ico, dic, street, city, zip, phone, email
	 */
	public function createAddress(array $data): Response
	{
		return $this->send('adb:addressbook', '2.0', [
			'adb:addressbookHeader' => array_filter([
				'adb:identity' => [
					'typ:address' => array_filter([
						'typ:company' => $data['company'] ?? '',
						'typ:ico' => $data['ico'] ?? null,
						'typ:dic' => $data['dic'] ?? null,
						'typ:street' => $data['street'] ?? null,
						'typ:city' => $data['city'] ?? null,
						'typ:zip' => $data['zip'] ?? null,
					], fn($v) => $v !== null && $v !== ''),
				],
				'adb:phone' => $data['phone'] ?? null,
				'adb:email' => $data['email'] ?? null,
			], fn($v) => $v !== null && $v !== ''),
		], 'Create address');
	}


	/**
	 * @param array<string, mixed> $data  Keys: code, name, sellingPrice, unit, storage, vatRate, etc.
	 */
	public function createStock(array $data): Response
	{
		$vatRate = $data['vatRate'] ?? 'high';

		$header = array_filter([
			'stk:stockType' => 'card',
			'stk:code' => $data['code'],
			'stk:EAN' => $data['EAN'] ?? null,
			'stk:PLU' => ($data['PLU'] ?? 0) ?: null,
			'stk:isSales' => $data['isSales'] ?? true,
			'stk:isInternet' => $data['isInternet'] ?? false,
			'stk:name' => $data['name'],
			'stk:nameComplement' => $data['nameComplement'] ?? null,
			'stk:unit' => $data['unit'] ?? 'ks',
			'stk:storage' => self::ids($data['storage'] ?? ''),
			'stk:sellingRateVAT' => $vatRate,
			'stk:purchasingRateVAT' => $vatRate,
			'stk:purchasingPrice' => ($data['purchasingPrice'] ?? 0) > 0 ? (float) $data['purchasingPrice'] : null,
			'stk:sellingPrice' => (float) $data['sellingPrice'],
			'stk:limitMin' => ($data['limitMin'] ?? 0) > 0 ? (float) $data['limitMin'] : null,
			'stk:limitMax' => ($data['limitMax'] ?? 0) > 0 ? (float) $data['limitMax'] : null,
			'stk:mass' => ($data['mass'] ?? 0) > 0 ? (float) $data['mass'] : null,
			'stk:supplier' => ($data['supplierId'] ?? 0) > 0 ? ['typ:id' => (int) $data['supplierId']] : null,
			'stk:shortName' => $data['shortName'] ?? null,
			'stk:guaranteeType' => ($data['guarantee'] ?? 0) > 0 ? ($data['guaranteeType'] ?? 'year') : null,
			'stk:guarantee' => ($data['guarantee'] ?? 0) > 0 ? (int) $data['guarantee'] : null,
			'stk:description' => $data['description'] ?? null,
			'stk:description2' => $data['description2'] ?? null,
			'stk:note' => $data['note'] ?? null,
		], fn($v) => $v !== null && $v !== '');

		return $this->send('stk:stock', '2.0', ['stk:stockHeader' => $header], 'Create stock');
	}


	/**
	 * @param array<string, string> $header  Keys: type, partnerName, date, partnerIco
	 * @param list<array<string, mixed>> $items
	 */
	public function createOrder(array $header, array $items): Response
	{
		$type = match ($header['type'] ?? '') {
			'issuedOrder' => 'issuedOrder',
			default => 'receivedOrder',
		};

		$orderItems = [];
		foreach ($items as $item) {
			$orderItems[] = ['ord:orderItem' => array_filter([
				'ord:text' => $item['text'] ?? '',
				'ord:quantity' => (float) ($item['quantity'] ?? 1),
				'ord:unit' => $item['unit'] ?? null,
				'ord:rateVAT' => $item['vatRate'] ?? 'high',
				'ord:homeCurrency' => ['typ:unitPrice' => (float) ($item['unitPrice'] ?? 0)],
			], fn($v) => $v !== null && $v !== '')];
		}

		return $this->send('ord:order', '2.0', [
			'ord:orderHeader' => array_filter([
				'ord:orderType' => $type,
				'ord:date' => $header['date'] ?? '',
				'ord:partnerIdentity' => self::buildPartnerIdentity($header),
			], fn($v) => $v !== null && $v !== '' && $v !== []),
			'ord:orderDetail' => $orderItems,
		], 'Create order');
	}


	/**
	 * @param array{agenda: string, recordId: int, reportId: int, pdfPath?: string, pdfBase64?: bool, printer?: string, copies?: int, emailTo?: string, emailSubject?: string, emailBody?: string} $options
	 */
	public function printRecord(array $options): Response
	{
		$pdfPath = $options['pdfPath'] ?? '';
		$pdfBase64 = $options['pdfBase64'] ?? false;
		$emailTo = $options['emailTo'] ?? '';

		if (($pdfBase64 || $emailTo !== '') && $pdfPath === '') {
			throw new \InvalidArgumentException('pdfPath is required when using pdfBase64 or emailTo');
		}

		$printerSettings = [
			'prn:report' => ['prn:id' => $options['reportId']],
		];
		if (($options['printer'] ?? '') !== '') {
			$printerSettings['prn:printer'] = $options['printer'];
		}
		if ($pdfPath !== '') {
			$pdf = ['prn:fileName' => $pdfPath];
			if ($pdfBase64) {
				$pdf['prn:binaryData'] = ['prn:responseXml' => 'true'];
			}
			if ($emailTo !== '') {
				$pdf['prn:sendMail'] = array_filter([
					'prn:to' => ['prn:email' => $emailTo],
					'prn:subject' => $options['emailSubject'] ?? null,
					'prn:body' => $options['emailBody'] ?? null,
				], fn($v) => $v !== null && $v !== '');
			}
			$printerSettings['prn:pdf'] = $pdf;
		}
		if (($options['copies'] ?? 1) > 1) {
			$printerSettings['prn:parameters'] = ['prn:copy' => min($options['copies'], 20)];
		}

		return $this->send('prn:print', '1.0', [
			'prn:record' => [
				'@agenda' => $options['agenda'],
				'ftr:filter' => ['ftr:id' => $options['recordId']],
			],
			'prn:printerSettings' => $printerSettings,
		], 'Print ' . $options['agenda']);
	}


	/**
	 * Send raw XML string as dataPackItem content.
	 */
	public function sendRawXml(string $innerXml, string $note = ''): Response
	{
		$xml = $this->xml->buildRaw($innerXml, $note);
		return new Response($this->httpPost($this->url . '/xml', $xml));
	}


	/********************* Helpers ****************d*g**/


	/**
	 * Build and send a structured request.
	 * @param array<string, mixed> $data
	 * @param array<string, string> $rootAttrs  Extra attributes on the root element
	 */
	private function send(string $rootElement, string $version, array $data, string $note, array $rootAttrs = []): Response
	{
		$xml = $this->xml->build($rootElement, $version, $data, $note, $rootAttrs);
		return new Response($this->httpPost($this->url . '/xml', $xml));
	}


	/**
	 * Shortcut for <typ:ids>$value</typ:ids> reference.
	 * @return array<string, string>|null
	 */
	private static function ids(string $value): ?array
	{
		return $value !== '' ? ['typ:ids' => $value] : null;
	}


	/**
	 * @param array<string, mixed> $header
	 * @return array<string, mixed>|null
	 */
	private static function buildPartnerIdentity(array $header): ?array
	{
		$partnerId = (int) ($header['partnerId'] ?? 0);
		if ($partnerId > 0) {
			return ['typ:id' => $partnerId];
		}

		$name = $header['partnerName'] ?? '';
		if ($name === '') {
			return null;
		}

		return ['typ:address' => array_filter([
			'typ:company' => $name,
			'typ:ico' => $header['partnerIco'] ?? null,
			'typ:street' => $header['partnerStreet'] ?? null,
			'typ:city' => $header['partnerCity'] ?? null,
			'typ:zip' => $header['partnerZip'] ?? null,
		], fn($v) => $v !== null && $v !== '')];
	}


	/**
	 * @return array<string, string>|null
	 */
	private static function buildPaymentType(string $paymentType): ?array
	{
		if ($paymentType === '') {
			return null;
		}
		return in_array($paymentType, ['draft', 'cash', 'card', 'compensation'], true)
			? ['typ:paymentType' => $paymentType]
			: ['typ:ids' => $paymentType];
	}


	/**
	 * @param array<string, mixed> $filter
	 * @return array<string, mixed>
	 */
	private static function buildFilterData(array $filter): array
	{
		$data = [];
		foreach ($filter as $key => $value) {
			if ($value === '' || $value === null) {
				continue;
			}
			$data += match ($key) {
				'id' => ['ftr:id' => (int) $value],
				'dateFrom' => ['ftr:dateFrom' => $value],
				'dateTill' => ['ftr:dateTill' => $value],
				'lastChanges' => ['ftr:lastChanges' => $value],
				'company' => ['ftr:selectedCompanys' => ['ftr:company' => $value]],
				'ico' => ['ftr:selectedIco' => ['ftr:ico' => $value]],
				'number' => ['ftr:selectedNumbers' => ['ftr:number' => ['typ:numberRequested' => $value]]],
				'code' => ['ftr:code' => $value],
				'name' => ['ftr:name' => $value],
				'EAN' => ['ftr:EAN' => $value],
				'storage' => ['ftr:storage' => ['typ:ids' => $value]],
				'store' => ['ftr:store' => ['typ:ids' => $value]],
				'internet' => ['ftr:internet' => (bool) $value],
				default => [],
			};
		}
		return $data;
	}


	/********************* Low-level transport ****************d*g**/


	/**
	 * @param list<string> $headers
	 */
	private function httpRequest(string $url, array $headers, ?string $postBody = null): string
	{
		$ch = curl_init($url);
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => $postBody !== null ? 120 : 30,
			CURLOPT_CONNECTTIMEOUT => 10,
		];
		if ($postBody !== null) {
			$opts[CURLOPT_POST] = true;
			$opts[CURLOPT_POSTFIELDS] = $postBody;
		}
		curl_setopt_array($ch, $opts);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);

		if (!is_string($response)) {
			throw new \RuntimeException('HTTP request failed: ' . $error);
		}
		if ($httpCode !== 200) {
			throw new \RuntimeException('mServer returned HTTP ' . $httpCode . ': ' . $response);
		}

		return $response;
	}


	private function httpPost(string $url, string $body): string
	{
		return $this->httpRequest($url, [
			'Content-Type: text/xml',
			'STW-Authorization: ' . $this->authHeader,
		], $body);
	}


	/**
	 * @param list<string> $headers
	 */
	private function httpGet(string $url, array $headers = []): string
	{
		return $this->httpRequest($url, $headers);
	}
}
