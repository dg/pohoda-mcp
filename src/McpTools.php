<?php declare(strict_types=1);

namespace DG\Pohoda;

use Mcp\Capability\Attribute\McpTool;
use function is_array;


class McpTools
{
	public function __construct(
		private PohodaClient $client,
	) {
	}


	/** Check Pohoda mServer status. */
	#[McpTool(name: 'pohoda_status')]
	public function status(): string
	{
		return self::json($this->client->getStatus());
	}


	/**
	 * List records from a Pohoda agenda with optional filtering.
	 * @param string $agenda One of: invoice, addressbook, stock, order, voucher, bank, contract, intDoc, offer, enquiry, vydejka, prijemka, prodejka, prevodka, vyroba, accountancy, store, bankAccount, cashRegister, numericalSeries, centre, activity
	 * @param string $invoiceType Required for invoice agenda: issuedInvoice or receivedInvoice
	 * @param int $id Filter by record ID
	 * @param string $dateFrom Filter documents from date (YYYY-MM-DD)
	 * @param string $dateTill Filter documents to date (YYYY-MM-DD)
	 * @param string $company Filter by company name
	 * @param string $ico Filter by company ICO
	 * @param string $number Filter by document number
	 * @param string $lastChanges Export records changed since (YYYY-MM-DDThh:mm:ss)
	 * @param string $code Filter stock by code
	 * @param string $name Filter stock by name
	 * @param string $EAN Filter stock by EAN barcode
	 * @param string $storage Filter stock by storage path (e.g. "ZBOZI/Elektro")
	 */
	#[McpTool(name: 'pohoda_list')]
	public function list(
		string $agenda,
		string $invoiceType = '',
		int $id = 0,
		string $dateFrom = '',
		string $dateTill = '',
		string $company = '',
		string $ico = '',
		string $number = '',
		string $lastChanges = '',
		string $code = '',
		string $name = '',
		string $EAN = '',
		string $storage = '',
	): string
	{
		$filter = array_filter([
			'id' => $id ?: null,
			'dateFrom' => $dateFrom,
			'dateTill' => $dateTill,
			'company' => $company,
			'ico' => $ico,
			'number' => $number,
			'lastChanges' => $lastChanges,
			'code' => $code,
			'name' => $name,
			'EAN' => $EAN,
			'storage' => $storage,
		], fn($v) => $v !== '' && $v !== null);

		return self::json($this->client->listRecords($agenda, $filter, $invoiceType)->toArray());
	}


	/**
	 * Create a new issued or received invoice in Pohoda.
	 * @param string $type issuedInvoice or receivedInvoice
	 * @param string $partnerName Company/person name
	 * @param string $date Invoice date (YYYY-MM-DD)
	 * @param string $items JSON array of items: [{"text":"Item","quantity":1,"unit":"ks","unitPrice":1000,"vatRate":"high","stockCode":"optional"}]
	 * @param string $partnerIco Company ID (ICO)
	 * @param string $partnerStreet Street address
	 * @param string $partnerCity City
	 * @param string $partnerZip ZIP code
	 * @param int $partnerId Address book ID (use instead of partner* fields to link to existing address)
	 * @param string $text Invoice description
	 * @param string $symVar Variable symbol
	 * @param string $dateDue Due date (YYYY-MM-DD)
	 * @param string $dateTax Tax date (YYYY-MM-DD)
	 * @param string $dateAccounting Accounting date (YYYY-MM-DD)
	 * @param string $paymentType Payment type: draft (bank transfer), cash, card, or IDS of payment method
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
	#[McpTool(name: 'pohoda_create_invoice')]
	public function createInvoice(
		string $type,
		string $partnerName = '',
		string $date = '',
		string $items = '[]',
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
		return self::json($this->client->createInvoice(
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
			self::parseItems($items),
		)->toArray());
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
	#[McpTool(name: 'pohoda_create_address')]
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
		return self::json($this->client->createAddress(
			compact('company', 'ico', 'dic', 'street', 'city', 'zip', 'phone', 'email'),
		)->toArray());
	}


	/**
	 * Create a new stock/inventory item in Pohoda.
	 * @param string $code Stock item code (unique identifier)
	 * @param string $name Stock item name
	 * @param float $sellingPrice Selling price
	 * @param string $unit Unit (default: ks)
	 * @param string $storage Storage/warehouse path (e.g. "ZBOZI/Elektro")
	 * @param string $vatRate VAT rate: none, low, high
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
	#[McpTool(name: 'pohoda_create_stock')]
	public function createStock(
		string $code,
		string $name,
		float $sellingPrice,
		string $unit = 'ks',
		string $storage = '',
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
		return self::json($this->client->createStock(
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
		)->toArray());
	}


	/**
	 * Create a new order in Pohoda.
	 * @param string $type receivedOrder or issuedOrder
	 * @param string $partnerName Company/person name
	 * @param string $date Order date (YYYY-MM-DD)
	 * @param string $items JSON array of items: [{"text":"Item","quantity":1,"unit":"ks","unitPrice":1000,"vatRate":"high"}]
	 * @param string $partnerIco Company ID (ICO)
	 */
	#[McpTool(name: 'pohoda_create_order')]
	public function createOrder(
		string $type,
		string $partnerName,
		string $date,
		string $items,
		string $partnerIco = '',
	): string
	{
		return self::json($this->client->createOrder(
			compact('type', 'partnerName', 'date', 'partnerIco'),
			self::parseItems($items),
		)->toArray());
	}


	/**
	 * Print a record from Pohoda to PDF or printer. Returns the result; when pdfBase64=true, the PDF content is returned as Base64.
	 * @param string $agenda Print agenda name: vydane_faktury, prijate_faktury, prijate_objednavky, vydane_objednavky, zasoby, adresar, pokladna, banka, interni_doklady, zakazky, vydejky, prijemky, prodejky, vydane_nabidky, prijate_nabidky, vydane_poptavky, prijate_poptavky, prevod, vyroba, sklady
	 * @param int $recordId ID of the record to print
	 * @param int $reportId Report template ID (find in report properties in Pohoda)
	 * @param string $pdfPath Save as PDF to this file path on the server (e.g. "C:\PDF\invoice.pdf"). Leave empty for printer output.
	 * @param bool $pdfBase64 If true, return the PDF content as Base64 in the response XML
	 * @param string $printer Printer name (from Pohoda print dialog). Leave empty for default printer.
	 * @param int $copies Number of copies (1-20)
	 * @param string $emailTo Send PDF by email to this address
	 * @param string $emailSubject Email subject
	 * @param string $emailBody Email body text
	 */
	#[McpTool(name: 'pohoda_print')]
	public function print(
		string $agenda,
		int $recordId,
		int $reportId,
		string $pdfPath = '',
		bool $pdfBase64 = false,
		string $printer = '',
		int $copies = 1,
		string $emailTo = '',
		string $emailSubject = '',
		string $emailBody = '',
	): string
	{
		return self::json($this->client->printRecord(
			compact('agenda', 'recordId', 'reportId', 'pdfPath', 'pdfBase64', 'printer', 'copies', 'emailTo', 'emailSubject', 'emailBody'),
		)->toArray());
	}


	/**
	 * Send raw XML to Pohoda mServer. The XML should be the inner content of a dataPackItem element
	 * with all necessary namespace declarations (e.g. a complete <inv:invoice> or <lStk:listStockRequest>).
	 * Use this for advanced operations not covered by other tools.
	 * @param string $xml Raw XML content for the dataPackItem
	 * @param string $note Optional note/description for the request
	 */
	#[McpTool(name: 'pohoda_raw_xml')]
	public function rawXml(string $xml, string $note = ''): string
	{
		return self::json($this->client->sendRawXml($xml, $note)->toArray());
	}


	/**
	 * @return list<array<string, mixed>>
	 */
	private static function parseItems(string $json): array
	{
		$items = json_decode($json, true);
		if (!is_array($items)) {
			throw new \InvalidArgumentException('Items must be a valid JSON array');
		}
		return array_values($items);
	}


	private static function json(mixed $data): string
	{
		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}
}
