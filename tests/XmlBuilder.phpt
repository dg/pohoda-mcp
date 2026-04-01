<?php declare(strict_types=1);

use DG\Pohoda\XmlBuilder;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


$builder = new XmlBuilder('12345678', 'Test');


test('produces valid XML with encoding', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', ['inv:invoiceHeader' => ['inv:invoiceType' => 'issuedInvoice']]);
	Assert::match('<?xml version="1.0" encoding="Windows-1250"?>%A%', $xml);

	$doc = new DOMDocument;
	Assert::true($doc->loadXML($xml));
});


test('dataPack contains ICO and application', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', []);
	Assert::match('%A%ico="12345678"%A%', $xml);
	Assert::match('%A%application="Test"%A%', $xml);
});


test('writes nested data recursively', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', [
		'inv:invoiceHeader' => [
			'inv:invoiceType' => 'issuedInvoice',
			'inv:date' => '2024-01-15',
			'inv:partnerIdentity' => [
				'typ:address' => [
					'typ:company' => 'Firma s.r.o.',
				],
			],
		],
	]);
	Assert::match('%A%<inv:invoiceType>issuedInvoice</inv:invoiceType>%A%', $xml);
	Assert::match('%A%<inv:date>2024-01-15</inv:date>%A%', $xml);
	Assert::match('%A%<typ:company>Firma s.r.o.</typ:company>%A%', $xml);
});


test('escapes special characters', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', [
		'inv:invoiceHeader' => ['inv:text' => 'A & B < "C"'],
	]);
	Assert::match('%A%A &amp; B &lt; &quot;C&quot;%A%', $xml);
});


test('skips null and empty string values', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', [
		'inv:invoiceHeader' => [
			'inv:invoiceType' => 'issuedInvoice',
			'inv:text' => null,
			'inv:note' => '',
		],
	]);
	Assert::false(str_contains($xml, '<inv:text'));
	Assert::false(str_contains($xml, '<inv:note'));
});


test('empty array generates empty element', function () use ($builder) {
	$xml = $builder->build('inv:invoice', '2.0', [
		'lst:requestInvoice' => [],
	]);
	Assert::match('%A%<lst:requestInvoice/>%A%', $xml);
});


test('writes boolean values', function () use ($builder) {
	$xml = $builder->build('stk:stock', '2.0', [
		'stk:stockHeader' => [
			'stk:isSales' => true,
			'stk:isInternet' => false,
		],
	]);
	Assert::match('%A%<stk:isSales>true</stk:isSales>%A%', $xml);
	Assert::match('%A%<stk:isInternet>false</stk:isInternet>%A%', $xml);
});


test('writes attributes from @-prefixed keys', function () use ($builder) {
	$xml = $builder->build('prn:print', '1.0', [
		'prn:record' => [
			'@agenda' => 'vydane_faktury',
			'ftr:filter' => ['ftr:id' => 42],
		],
	]);
	Assert::match('%A%agenda="vydane_faktury"%A%', $xml);
	Assert::match('%A%<ftr:id>42</ftr:id>%A%', $xml);
});


test('writes root attributes', function () use ($builder) {
	$xml = $builder->build('lst:listInvoiceRequest', '2.0', [], '', [
		'invoiceVersion' => '2.0',
		'invoiceType' => 'issuedInvoice',
	]);
	Assert::match('%A%invoiceVersion="2.0"%A%', $xml);
	Assert::match('%A%invoiceType="issuedInvoice"%A%', $xml);
});


test('handles numeric keys for array items', function () use ($builder) {
	$xml = $builder->build('ord:order', '2.0', [
		'ord:orderDetail' => [
			['ord:orderItem' => ['ord:text' => 'Item 1']],
			['ord:orderItem' => ['ord:text' => 'Item 2']],
		],
	]);
	Assert::match('%A%<ord:orderItem>%A?%Item 1%A?%</ord:orderItem>%A?%<ord:orderItem>%A?%Item 2%A?%</ord:orderItem>%A%', $xml);
});
