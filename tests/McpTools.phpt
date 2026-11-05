<?php declare(strict_types=1);

use DG\Pohoda\{McpTools, PohodaClient, Response};
use Mcp\Exception\ToolCallException;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


function toolsAnswering(string $xml): McpTools
{
	return new McpTools(new class ($xml) extends PohodaClient {
		public function __construct(
			private string $answer,
		) {
			parent::__construct('http://127.0.0.1:9', '12345678', 'Admin', '');
		}


		public function sendRawXml(string $innerXml, string $note = ''): Response
		{
			return new Response($this->answer);
		}
	});
}


function responseWithItem(string $itemState, string $note = '', string $nestedState = 'ok'): string
{
	return '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="ok"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd"'
		. ' xmlns:adb="http://www.stormware.cz/schema/version_2/addressbook.xsd">'
		. '<rsp:responsePackItem version="2.0" id="i1" state="' . $itemState . '" note="' . $note . '">'
		. '<adb:addressbookResponse state="' . $nestedState . '"/>'
		. '</rsp:responsePackItem>'
		. '</rsp:responsePack>';
}


test('tool returns the data of a successful response', function () {
	$data = json_decode(toolsAnswering(responseWithItem('ok'))->rawXml('<x/>'), true);
	Assert::same('ok', $data['state']);
	Assert::same('ok', $data['items'][0]['data']['@state']);
});


test('tool fails with the reason of an item error', function () {
	Assert::exception(
		fn() => toolsAnswering(responseWithItem('error', 'Schema validation failed'))->rawXml('<x/>'),
		ToolCallException::class,
		'Pohoda mServer did not report success: %A%Schema validation failed%A%',
	);
});


test('tool fails on an error of the nested response', function () {
	Assert::exception(
		fn() => toolsAnswering(responseWithItem('ok', nestedState: 'error'))->rawXml('<x/>'),
		ToolCallException::class,
		'Pohoda mServer did not report success: %A%',
	);
});


test('tool says an unreadable response leaves the outcome open', function () {
	Assert::exception(
		fn() => toolsAnswering('not xml at all')->rawXml('<x/>'),
		ToolCallException::class,
		'%A%may still have been processed%A%',
	);
});
