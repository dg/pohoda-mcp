<?php declare(strict_types=1);

use DG\Pohoda\Response;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


test('parses plain text status as error', function () {
	$r = new Response('not xml at all');
	Assert::same('error', $r->state);
	Assert::same([], $r->items);
	Assert::false($r->isOk());
});


test('parses ok response with programVersion', function () {
	$xml = '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="ok" programVersion="12345"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd">'
		. '<rsp:responsePackItem version="2.0" id="item01" state="ok">'
		. '</rsp:responsePackItem>'
		. '</rsp:responsePack>';
	$r = new Response($xml);
	Assert::true($r->isOk());
	Assert::same('12345', $r->programVersion);
	Assert::count(1, $r->items);
	Assert::same('item01', $r->items[0]->id);
	Assert::true($r->items[0]->isOk());
});


test('parses error response', function () {
	$xml = '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="error"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd">'
		. '</rsp:responsePack>';
	$r = new Response($xml);
	Assert::false($r->isOk());
	Assert::count(0, $r->items);
});


test('parses inner data from address book', function () {
	$xml = '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="ok"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd"'
		. ' xmlns:lAdb="http://www.stormware.cz/schema/version_2/list_addBook.xsd"'
		. ' xmlns:adb="http://www.stormware.cz/schema/version_2/addressbook.xsd"'
		. ' xmlns:typ="http://www.stormware.cz/schema/version_2/type.xsd">'
		. '<rsp:responsePackItem version="2.0" id="i1" state="ok">'
		. '<lAdb:listAddressBook version="2.0" state="ok">'
		. '<lAdb:addressbook version="2.0">'
		. '<adb:addressbookHeader>'
		. '<adb:id>8</adb:id>'
		. '<adb:identity><typ:address>'
		. '<typ:company>Test s.r.o.</typ:company>'
		. '<typ:ico>12345678</typ:ico>'
		. '</typ:address></adb:identity>'
		. '</adb:addressbookHeader>'
		. '</lAdb:addressbook>'
		. '</lAdb:listAddressBook>'
		. '</rsp:responsePackItem>'
		. '</rsp:responsePack>';
	$r = new Response($xml);
	$addr = $r->items[0]->data['addressbook']['addressbookHeader'];
	Assert::same('8', $addr['id']);
	Assert::same('Test s.r.o.', $addr['identity']['address']['company']);
});


test('groups multiple children into array', function () {
	$xml = '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="ok"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd"'
		. ' xmlns:lst="http://www.stormware.cz/schema/version_2/list.xsd"'
		. ' xmlns:inv="http://www.stormware.cz/schema/version_2/invoice.xsd">'
		. '<rsp:responsePackItem version="2.0" id="i1" state="ok">'
		. '<lst:listInvoice version="2.0" state="ok">'
		. '<lst:invoice version="2.0"><inv:invoiceHeader><inv:id>1</inv:id></inv:invoiceHeader></lst:invoice>'
		. '<lst:invoice version="2.0"><inv:invoiceHeader><inv:id>2</inv:id></inv:invoiceHeader></lst:invoice>'
		. '<lst:invoice version="2.0"><inv:invoiceHeader><inv:id>3</inv:id></inv:invoiceHeader></lst:invoice>'
		. '</lst:listInvoice>'
		. '</rsp:responsePackItem>'
		. '</rsp:responsePack>';
	$r = new Response($xml);
	$invoices = $r->items[0]->data['invoice'];
	Assert::count(3, $invoices);
	Assert::same('1', $invoices[0]['invoiceHeader']['id']);
	Assert::same('3', $invoices[2]['invoiceHeader']['id']);
});


test('toArray() produces expected structure', function () {
	$xml = '<?xml version="1.0" encoding="Windows-1250"?>'
		. '<rsp:responsePack version="2.0" id="t1" state="ok" programVersion="v1"'
		. ' xmlns:rsp="http://www.stormware.cz/schema/version_2/response.xsd">'
		. '<rsp:responsePackItem version="2.0" id="x1" state="ok">'
		. '</rsp:responsePackItem>'
		. '</rsp:responsePack>';
	$arr = (new Response($xml))->toArray();
	Assert::same('ok', $arr['state']);
	Assert::same('v1', $arr['programVersion']);
	Assert::count(1, $arr['items']);
	Assert::same('x1', $arr['items'][0]['id']);
});
