<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use DG\Pohoda\McpTools;
use DG\Pohoda\MServerController;
use DG\Pohoda\PohodaClient;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

$client = new PohodaClient(
	url: getenv('POHODA_URL') ?: 'http://localhost:444',
	ico: getenv('POHODA_ICO') ?: '',
	username: getenv('POHODA_USERNAME') ?: '',
	password: getenv('POHODA_PASSWORD') ?: '',
);

// Optional auto-start of mServer on first tool call (Windows-only).
$exePath = getenv('POHODA_EXE_PATH');
$configName = getenv('POHODA_CONFIG_NAME');
if ($exePath && $configName) {
	$client->setController(new MServerController($exePath, $configName));
}

$server = Server::builder()
	->setServerInfo('pohoda-mserver', '1.0.0', 'MCP server for Pohoda accounting software')
	->setInstructions(<<<'TEXT'
		Pohoda MCP server interacts with Pohoda accounting software via its mServer XML API.

		Listing tools split by record kind:
		  - list_documents: invoices, orders, vouchers, bank, contracts, intDoc, offers, enquiries, vydejka/prijemka/prodejka/prevodka/vyroba, accountancy. Invoice agenda requires invoiceType.
		  - list_stock: stock/inventory items.
		  - list_contacts: address book.
		  - Niche/codebook agendas (centre, activity, store, bankAccount, cashRegister, numericalSeries) are reachable only via raw_xml.

		Creation tools: create_invoice, create_order, create_address, create_stock. Invoice and order items are passed as a JSON array of objects; see the items schema in each tool.

		Output: print exports records to PDF or sends them to a printer; the agenda parameter takes Czech names (vydane_faktury, zasoby, ...).

		Reference data is exposed as resources under pohoda://enums/* (agendas, vat-rates, payment-types, print-agendas) for browsing allowed values.

		status reports connectivity. raw_xml covers operations not exposed by dedicated tools and can write or delete data.
		TEXT)
	->setContainer(new class ($client) implements \Psr\Container\ContainerInterface {
		public function __construct(
			private PohodaClient $client,
		) {
		}


		public function get(string $id): object
		{
			return new $id($this->client);
		}


		public function has(string $id): bool
		{
			return $id === McpTools::class;
		}
	})
	->setDiscovery(__DIR__ . '/src', ['.'])
	->build();

$server->run(new StdioTransport);
