<?php declare(strict_types=1);

namespace DG\Pohoda;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;


class McpTools
{
	private const VatRates = ['none', 'low', 'high'];

	private const DocumentAgendas = [
		'invoice', 'order', 'voucher', 'bank', 'contract', 'intDoc', 'offer', 'enquiry',
		'vydejka', 'prijemka', 'prodejka', 'prevodka', 'vyroba', 'accountancy',
	];

	private const DefaultListLimit = 100;

	private const ItemSchema = [
		'type' => 'object',
		'properties' => [
			'text' => ['type' => 'string', 'description' => 'Item description'],
			'quantity' => ['type' => 'number', 'description' => 'Quantity', 'default' => 1],
			'unit' => ['type' => 'string', 'description' => 'Unit (e.g. ks, kg, h)', 'default' => 'ks'],
			'unitPrice' => ['type' => 'number', 'description' => 'Unit price excluding VAT'],
			'vatRate' => ['type' => 'string', 'enum' => self::VatRates, 'default' => 'high'],
			'stockCode' => ['type' => 'string', 'description' => 'Optional stock item code reference'],
		],
		'required' => ['text', 'unitPrice'],
	];


	public function __construct(
		private PohodaClient $client,
	) {
	}


	/**
	 * Check Pohoda mServer status: returns version, license info, listening port, and active company.
	 * With companyDetail=true also returns the active company name, database name, and accounting year (requires authenticated request).
	 */
	#[McpTool(
		name: 'status',
		title: 'Check Pohoda mServer status',
		annotations: new ToolAnnotations(readOnlyHint: true, idempotentHint: true, openWorldHint: true),
	)]
	public function status(bool $companyDetail = false): string
	{
		try {
			$data = $this->client->getStatus($companyDetail);
		} catch (\Throwable $e) {
			throw new ToolCallException('Pohoda mServer status check failed: ' . $e->getMessage() . '. Verify POHODA_URL and that mServer is running.');
		}
		return self::json($data);
	}


	/**
	 * List transactional documents (invoices, orders, vouchers, etc.) from Pohoda with optional filtering.
	 * For invoice agenda the invoiceType parameter is required.
	 * For stock/inventory items use list_stock; for the address book use list_contacts.
	 * Niche agendas (centre, activity, store, bankAccount, cashRegister, numericalSeries) are reachable only via raw_xml.
	 *
	 * @param int $id Filter by record ID
	 * @param string $dateFrom Filter from date (YYYY-MM-DD)
	 * @param string $dateTill Filter to date (YYYY-MM-DD)
	 * @param string $company Filter by company name
	 * @param string $ico Filter by company ICO
	 * @param string $number Filter by document number — exact full-value match, not substring
	 * @param string $lastChanges Records changed since (YYYY-MM-DDThh:mm:ss)
	 * @param int $limit Max records to return (client-side truncation, default 100)
	 */
	#[McpTool(
		name: 'list_documents',
		title: 'List Pohoda documents',
		annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: true),
	)]
	public function listDocuments(
		#[Schema(enum: self::DocumentAgendas)]
		string $agenda,
		#[Schema(enum: ['', 'issuedInvoice', 'receivedInvoice'], description: 'Required for invoice agenda')]
		string $invoiceType = '',
		int $id = 0,
		string $dateFrom = '',
		string $dateTill = '',
		string $company = '',
		string $ico = '',
		string $number = '',
		string $lastChanges = '',
		int $limit = self::DefaultListLimit,
	): string
	{
		$filter = self::filter([
			'id' => $id ?: null,
			'dateFrom' => $dateFrom,
			'dateTill' => $dateTill,
			'company' => $company,
			'ico' => $ico,
			'number' => $number,
			'lastChanges' => $lastChanges,
		]);
		return self::jsonList($this->assertOk($this->client->listRecords($agenda, $filter, $invoiceType)), $limit);
	}


	/**
	 * List stock/inventory items from Pohoda with optional filtering.
	 * For documents (invoices, orders, ...) use list_documents; for the address book use list_contacts.
	 *
	 * @param int $id Filter by record ID
	 * @param string $code Filter by stock code
	 * @param string $name Filter by name
	 * @param string $EAN Filter by EAN barcode
	 * @param string $storage Filter by storage path (e.g. "ZBOZI/Elektro")
	 * @param string $store Filter by store IDS
	 * @param string $lastChanges Records changed since (YYYY-MM-DDThh:mm:ss)
	 * @param int $limit Max records to return (client-side truncation, default 100)
	 */
	#[McpTool(
		name: 'list_stock',
		title: 'List Pohoda stock items',
		annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: true),
	)]
	public function listStock(
		int $id = 0,
		string $code = '',
		string $name = '',
		string $EAN = '',
		string $storage = '',
		string $store = '',
		?bool $internet = null,
		string $lastChanges = '',
		int $limit = self::DefaultListLimit,
	): string
	{
		$filter = self::filter([
			'id' => $id ?: null,
			'code' => $code,
			'name' => $name,
			'EAN' => $EAN,
			'storage' => $storage,
			'store' => $store,
			'internet' => $internet,
			'lastChanges' => $lastChanges,
		]);
		return self::jsonList($this->assertOk($this->client->listRecords('stock', $filter)), $limit);
	}


	/**
	 * List contacts (companies/persons) from the Pohoda address book with optional filtering.
	 * For documents use list_documents; for stock/inventory use list_stock.
	 *
	 * @param int $id Filter by record ID
	 * @param string $company Filter by company name
	 * @param string $ico Filter by ICO
	 * @param string $lastChanges Records changed since (YYYY-MM-DDThh:mm:ss)
	 * @param int $limit Max records to return (client-side truncation, default 100)
	 */
	#[McpTool(
		name: 'list_contacts',
		title: 'List Pohoda contacts',
		annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: true),
	)]
	public function listContacts(
		int $id = 0,
		string $company = '',
		string $ico = '',
		string $lastChanges = '',
		int $limit = self::DefaultListLimit,
	): string
	{
		$filter = self::filter([
			'id' => $id ?: null,
			'company' => $company,
			'ico' => $ico,
			'lastChanges' => $lastChanges,
		]);
		return self::jsonList($this->assertOk($this->client->listRecords('addressbook', $filter)), $limit);
	}


	/**
	 * Create a new issued or received invoice in Pohoda.
	 * Most invoices need only: type, partnerName (or partnerId), date, items. The remaining parameters are advanced.
	 * Either link to an existing contact via partnerId, or supply partnerName/partnerIco/partnerStreet/etc. — not both.
	 *
	 * @param string $type issuedInvoice or receivedInvoice
	 * @param list<array<string, mixed>> $items Invoice line items
	 * @param string $partnerName Company/person name (use when not linking via partnerId)
	 * @param string $date Invoice date (YYYY-MM-DD)
	 * @param string $partnerIco Company ID (ICO)
	 * @param string $partnerStreet Street address
	 * @param string $partnerCity City
	 * @param string $partnerZip ZIP code
	 * @param int $partnerId Address book ID — links to an existing contact instead of inline partner fields
	 * @param string $text Invoice description
	 * @param string $symVar Variable symbol
	 * @param string $dateDue Due date (YYYY-MM-DD)
	 * @param string $dateTax Tax date (YYYY-MM-DD)
	 * @param string $dateAccounting Accounting date (YYYY-MM-DD)
	 * @param string $accountIds Bank account IDS (e.g. "KB")
	 * @param string $accounting Pre-accounting IDS (e.g. "3FV")
	 * @param string $classificationVAT VAT classification: inland, nonSubsume, etc.
	 * @param string $centre Centre IDS (e.g. "BRNO")
	 * @param string $activity Activity IDS (e.g. "SLUZBY")
	 * @param string $contract Contract IDS (e.g. "10Zak00002")
	 * @param string $currency Foreign currency code (e.g. "EUR"); leave empty for domestic
	 * @param float $currencyRate Exchange rate (required with currency)
	 * @param string $note Note
	 * @param string $intNote Internal note
	 */
	#[McpTool(
		name: 'create_invoice',
		title: 'Create Pohoda invoice',
		annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false, openWorldHint: true),
	)]
	public function createInvoice(
		#[Schema(enum: ['issuedInvoice', 'receivedInvoice'])]
		string $type,
		#[Schema(items: self::ItemSchema, minItems: 1, description: 'Invoice line items')]
		array $items,
		string $partnerName = '',
		string $date = '',
		string $partnerIco = '',
		string $partnerStreet = '',
		string $partnerCity = '',
		string $partnerZip = '',
		int $partnerId = 0,
		string $text = '',
		string $symVar = '',
		string $dateDue = '',
		string $dateTax = '',
		string $dateAccounting = '',
		#[Schema(description: 'Payment type: one of draft (bank transfer), cash, card, compensation, or a custom payment-method IDS')]
		string $paymentType = '',
		string $accountIds = '',
		string $accounting = '',
		string $classificationVAT = '',
		string $centre = '',
		string $activity = '',
		string $contract = '',
		string $currency = '',
		float $currencyRate = 0,
		string $note = '',
		string $intNote = '',
	): string
	{
		return self::json($this->assertOk($this->client->createInvoice(
			compact(
				'type',
				'partnerName',
				'date',
				'partnerIco',
				'partnerStreet',
				'partnerCity',
				'partnerZip',
				'partnerId',
				'text',
				'symVar',
				'dateDue',
				'dateTax',
				'dateAccounting',
				'paymentType',
				'accountIds',
				'accounting',
				'classificationVAT',
				'centre',
				'activity',
				'contract',
				'currency',
				'currencyRate',
				'note',
				'intNote',
			),
			$items,
		)));
	}


	/**
	 * Create a new address book entry (contact/company) in Pohoda.
	 * @param string $company Company name
	 * @param string $ico Company ID (ICO)
	 * @param string $dic Tax ID (DIC)
	 * @param string $street Street address
	 * @param string $city City
	 * @param string $zip ZIP code
	 * @param string $phone Phone number
	 * @param string $email Email address
	 */
	#[McpTool(
		name: 'create_address',
		title: 'Create Pohoda contact',
		annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false, openWorldHint: true),
	)]
	public function createAddress(
		string $company,
		string $ico = '',
		string $dic = '',
		string $street = '',
		string $city = '',
		string $zip = '',
		string $phone = '',
		string $email = '',
	): string
	{
		return self::json($this->assertOk($this->client->createAddress(
			compact('company', 'ico', 'dic', 'street', 'city', 'zip', 'phone', 'email'),
		)));
	}


	/**
	 * Create a new stock/inventory item in Pohoda.
	 * @param string $code Stock item code (unique identifier)
	 * @param string $name Stock item name
	 * @param float $sellingPrice Selling price
	 * @param string $unit Unit (default: ks)
	 * @param string $storage Storage/warehouse path (e.g. "ZBOZI/Elektro")
	 * @param float $purchasingPrice Purchasing/cost price
	 * @param string $EAN EAN barcode
	 * @param int $PLU PLU code for cash registers
	 * @param bool $isSales Whether available for sale
	 * @param bool $isInternet Whether available on e-shop
	 * @param string $description Product description
	 * @param string $description2 Additional product description
	 * @param float $limitMin Minimum stock level
	 * @param float $limitMax Maximum stock level
	 * @param float $mass Weight in kg
	 * @param int $supplierId Supplier address book ID
	 * @param int $guarantee Guarantee period
	 * @param string $guaranteeType Guarantee type: year, month
	 * @param string $shortName Short name for receipts
	 * @param string $nameComplement Name complement
	 * @param string $note Note
	 */
	#[McpTool(
		name: 'create_stock',
		title: 'Create Pohoda stock item',
		annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false, openWorldHint: true),
	)]
	public function createStock(
		string $code,
		string $name,
		float $sellingPrice,
		string $unit = 'ks',
		string $storage = '',
		#[Schema(enum: self::VatRates)]
		string $vatRate = 'high',
		float $purchasingPrice = 0,
		string $EAN = '',
		int $PLU = 0,
		bool $isSales = true,
		bool $isInternet = false,
		string $description = '',
		string $description2 = '',
		float $limitMin = 0,
		float $limitMax = 0,
		float $mass = 0,
		int $supplierId = 0,
		int $guarantee = 0,
		string $guaranteeType = 'year',
		string $shortName = '',
		string $nameComplement = '',
		string $note = '',
	): string
	{
		return self::json($this->assertOk($this->client->createStock(
			compact(
				'code',
				'name',
				'sellingPrice',
				'unit',
				'storage',
				'vatRate',
				'purchasingPrice',
				'EAN',
				'PLU',
				'isSales',
				'isInternet',
				'description',
				'description2',
				'limitMin',
				'limitMax',
				'mass',
				'supplierId',
				'guarantee',
				'guaranteeType',
				'shortName',
				'nameComplement',
				'note',
			),
		)));
	}


	/**
	 * Create a new order in Pohoda.
	 *
	 * @param string $type receivedOrder or issuedOrder
	 * @param string $partnerName Company/person name
	 * @param string $date Order date (YYYY-MM-DD)
	 * @param list<array<string, mixed>> $items Order line items
	 * @param string $partnerIco Company ID (ICO)
	 */
	#[McpTool(
		name: 'create_order',
		title: 'Create Pohoda order',
		annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false, openWorldHint: true),
	)]
	public function createOrder(
		#[Schema(enum: ['receivedOrder', 'issuedOrder'])]
		string $type,
		string $partnerName,
		string $date,
		#[Schema(items: self::ItemSchema, minItems: 1, description: 'Order line items')]
		array $items,
		string $partnerIco = '',
	): string
	{
		return self::json($this->assertOk($this->client->createOrder(
			compact('type', 'partnerName', 'date', 'partnerIco'),
			$items,
		)));
	}


	/**
	 * Print a record from Pohoda — either to a printer or as PDF (saved to a file on the mServer machine, optionally also returned as Base64).
	 * The pdfPath is on the machine running mServer, not the Claude client.
	 * Provide either pdfPath (PDF output) or printer (printer output); if neither is set, the default printer is used.
	 *
	 * @param int $recordId ID of the record to print
	 * @param int $reportId Report template ID (find in report properties in Pohoda)
	 * @param string $pdfPath Save as PDF to this server-side file path (e.g. "C:\PDF\invoice.pdf"). Leave empty for printer output.
	 * @param bool $pdfBase64 If true, also return the PDF content as Base64 in the response (requires pdfPath)
	 * @param string $printer Printer name (from Pohoda print dialog). Leave empty for default printer.
	 * @param int $copies Number of copies (1-20)
	 */
	#[McpTool(
		name: 'print',
		title: 'Print Pohoda record',
		annotations: new ToolAnnotations(destructiveHint: false, idempotentHint: false, openWorldHint: true),
	)]
	public function print(
		#[Schema(enum: [
			'vydane_faktury', 'prijate_faktury', 'prijate_objednavky', 'vydane_objednavky', 'zasoby', 'adresar',
			'pokladna', 'banka', 'interni_doklady', 'zakazky', 'vydejky', 'prijemky', 'prodejky',
			'vydane_nabidky', 'prijate_nabidky', 'vydane_poptavky', 'prijate_poptavky', 'prevod', 'vyroba', 'sklady',
		])]
		string $agenda,
		int $recordId,
		int $reportId,
		string $pdfPath = '',
		bool $pdfBase64 = false,
		string $printer = '',
		int $copies = 1,
	): string
	{
		return self::json($this->assertOk($this->client->printRecord(
			compact('agenda', 'recordId', 'reportId', 'pdfPath', 'pdfBase64', 'printer', 'copies'),
		)));
	}


	/**
	 * Send raw XML to Pohoda mServer. The XML should be the inner content of a dataPackItem element
	 * with all necessary namespace declarations (e.g. a complete <inv:invoice> or <lStk:listStockRequest>).
	 * Covers operations not exposed by dedicated tools and can write or delete data.
	 *
	 * Pohoda XML schema documentation: https://www.stormware.cz/pohoda/xml/
	 *
	 * @param string $xml Raw XML content for the dataPackItem
	 * @param string $note Optional note/description for the request
	 */
	#[McpTool(
		name: 'raw_xml',
		title: 'Send raw Pohoda XML',
		annotations: new ToolAnnotations(destructiveHint: true, idempotentHint: false, openWorldHint: true),
	)]
	public function rawXml(string $xml, string $note = ''): string
	{
		return self::json($this->assertOk($this->client->sendRawXml($xml, $note)));
	}


	/********************* Resources ****************d*g**/


	/**
	 * List of Pohoda agendas accessible via the XML API, grouped by which list tool covers them.
	 *
	 * @return array<string, array{tool: string, agendas: list<string>}>
	 */
	#[McpResource(uri: 'pohoda://enums/agendas', mimeType: 'application/json')]
	public function agendasResource(): array
	{
		return [
			'documents' => [
				'tool' => 'list_documents',
				'agendas' => self::DocumentAgendas,
			],
			'stock' => [
				'tool' => 'list_stock',
				'agendas' => ['stock'],
			],
			'contacts' => [
				'tool' => 'list_contacts',
				'agendas' => ['addressbook'],
			],
			'codebooks_only_via_raw_xml' => [
				'tool' => 'raw_xml',
				'agendas' => ['centre', 'activity', 'store', 'bankAccount', 'cashRegister', 'numericalSeries'],
			],
		];
	}


	/**
	 * Allowed values for invoice/order item vatRate.
	 *
	 * @return array<string, string>
	 */
	#[McpResource(uri: 'pohoda://enums/vat-rates', mimeType: 'application/json')]
	public function vatRatesResource(): array
	{
		return [
			'none' => 'No VAT (exempt)',
			'low' => 'Reduced VAT rate',
			'high' => 'Standard VAT rate',
		];
	}


	/**
	 * Allowed values for invoice paymentType. Custom payment-method IDS (configured in Pohoda) are also accepted.
	 *
	 * @return array<string, string>
	 */
	#[McpResource(uri: 'pohoda://enums/payment-types', mimeType: 'application/json')]
	public function paymentTypesResource(): array
	{
		return [
			'draft' => 'Bank transfer / draft',
			'cash' => 'Cash',
			'card' => 'Card payment',
			'compensation' => 'Compensation/offset',
		];
	}


	/**
	 * Czech agenda names accepted by print.
	 *
	 * @return array<string, string>
	 */
	#[McpResource(uri: 'pohoda://enums/print-agendas', mimeType: 'application/json')]
	public function printAgendasResource(): array
	{
		return [
			'vydane_faktury' => 'Issued invoices',
			'prijate_faktury' => 'Received invoices',
			'prijate_objednavky' => 'Received orders',
			'vydane_objednavky' => 'Issued orders',
			'zasoby' => 'Stock items',
			'adresar' => 'Address book',
			'pokladna' => 'Cash register',
			'banka' => 'Bank',
			'interni_doklady' => 'Internal documents',
			'zakazky' => 'Contracts',
			'vydejky' => 'Issue notes',
			'prijemky' => 'Receipt notes',
			'prodejka' => 'Sales receipts',
			'prodejky' => 'Sales receipts',
			'vydane_nabidky' => 'Issued offers',
			'prijate_nabidky' => 'Received offers',
			'vydane_poptavky' => 'Issued enquiries',
			'prijate_poptavky' => 'Received enquiries',
			'prevod' => 'Transfers',
			'vyroba' => 'Production',
			'sklady' => 'Warehouses',
		];
	}


	/********************* Helpers ****************d*g**/


	/**
	 * Returns the response payload as an array, or throws ToolCallException so the SDK
	 * propagates an isError result instead of surfacing the failure as a successful JSON dump.
	 *
	 * @return array<string, mixed>
	 */
	private function assertOk(Response $response): array
	{
		$data = $response->toArray();
		if (!$response->isOk()) {
			throw new ToolCallException('Pohoda mServer returned error: ' . self::json($data));
		}
		return $data;
	}


	/**
	 * @param array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private static function filter(array $values): array
	{
		return array_filter($values, fn($v) => $v !== '' && $v !== null);
	}


	private static function json(mixed $data): string
	{
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}


	/**
	 * Encodes a list response, truncating items[0].data lists past $limit and appending a hint.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function jsonList(array $data, int $limit): string
	{
		$limit = max(1, $limit);
		$items = $data['items'] ?? [];
		$first = $items[0] ?? null;

		if (is_array($first) && isset($first['data']) && is_array($first['data'])) {
			foreach ($first['data'] as $key => $value) {
				if (is_array($value) && array_is_list($value) && count($value) > $limit) {
					$total = count($value);
					$first['data'][$key] = array_slice($value, 0, $limit);
					$first['truncated'] = "Showing first $limit of $total records. Refine filters to narrow.";
					$items[0] = $first;
					$data['items'] = $items;
					break;
				}
			}
		}

		return self::json($data);
	}
}
