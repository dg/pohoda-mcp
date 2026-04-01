<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use DG\Pohoda\McpTools;
use DG\Pohoda\PohodaClient;
use Mcp\Server;
use Mcp\Server\Transport\StdioTransport;

$client = new PohodaClient(
	url: getenv('POHODA_URL') ?: 'http://localhost:444',
	ico: getenv('POHODA_ICO') ?: '',
	username: getenv('POHODA_USERNAME') ?: '',
	password: getenv('POHODA_PASSWORD') ?: '',
);

$server = Server::builder()
	->setServerInfo('pohoda-mserver', '1.0.0', 'MCP server for Pohoda accounting software')
	->setInstructions(<<<'TEXT'
		Pohoda MCP server provides tools to interact with Pohoda accounting software via its mServer XML API.
		Use pohoda_status to check server connectivity.
		Use pohoda_list to query agendas (invoices, stock, addressbook, orders, etc.).
		For invoice listing, the invoiceType parameter is required (issuedInvoice or receivedInvoice).
		Use pohoda_create_invoice, pohoda_create_address, pohoda_create_stock, pohoda_create_order to create records.
		Use pohoda_print to print or export records to PDF. Agenda names are in Czech (vydane_faktury, zasoby, etc.).
		Use pohoda_raw_xml for advanced XML operations not covered by other tools.
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
