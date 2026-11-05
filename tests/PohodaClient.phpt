<?php declare(strict_types=1);

use DG\Pohoda\PohodaClient;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';


function buildFilterData(string $agenda, array $filter): array
{
	return Assert::with(PohodaClient::class, fn() => self::buildFilterData($agenda, $filter));
}


test('address book has company and ico directly in the filter', function () {
	Assert::same(
		['ftr:company' => 'Firma', 'ftr:ico' => '12345678'],
		buildFilterData('addressbook', ['company' => 'Firma', 'ico' => '12345678']),
	);
});


test('documents wrap company and ico', function () {
	Assert::same(
		[
			'ftr:selectedCompanys' => ['ftr:company' => 'Firma'],
			'ftr:selectedIco' => ['ftr:ico' => '12345678'],
		],
		buildFilterData('invoice', ['company' => 'Firma', 'ico' => '12345678']),
	);
});
