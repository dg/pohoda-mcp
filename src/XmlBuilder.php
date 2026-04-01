<?php declare(strict_types=1);

namespace DG\Pohoda;

use function is_array, is_bool, is_int, is_scalar, is_string, sprintf;


/**
 * Builds Pohoda XML requests using XMLWriter.
 * Data is specified as nested PHP arrays, written recursively.
 */
final class XmlBuilder
{
	private const Namespaces = [
		'dat' => 'http://www.stormware.cz/schema/version_2/data.xsd',
		'typ' => 'http://www.stormware.cz/schema/version_2/type.xsd',
		'ftr' => 'http://www.stormware.cz/schema/version_2/filter.xsd',
		'inv' => 'http://www.stormware.cz/schema/version_2/invoice.xsd',
		'ord' => 'http://www.stormware.cz/schema/version_2/order.xsd',
		'adb' => 'http://www.stormware.cz/schema/version_2/addressbook.xsd',
		'stk' => 'http://www.stormware.cz/schema/version_2/stock.xsd',
		'prn' => 'http://www.stormware.cz/schema/version_2/print.xsd',
		'lst' => 'http://www.stormware.cz/schema/version_2/list.xsd',
		'lStk' => 'http://www.stormware.cz/schema/version_2/list_stock.xsd',
		'lAdb' => 'http://www.stormware.cz/schema/version_2/list_addBook.xsd',
		'lCon' => 'http://www.stormware.cz/schema/version_2/list_contract.xsd',
		'lCen' => 'http://www.stormware.cz/schema/version_2/list_centre.xsd',
		'lAcv' => 'http://www.stormware.cz/schema/version_2/list_activity.xsd',
	];

	private \XMLWriter $w;


	public function __construct(
		private readonly string $ico,
		private readonly string $application = 'MCP Server',
	) {
	}


	/**
	 * Build complete dataPack XML with one dataPackItem.
	 * @param array<string, mixed> $data
	 * @param array<string, string> $rootAttrs
	 */
	public function build(
		string $rootElement,
		string $version,
		array $data,
		string $note = '',
		array $rootAttrs = [],
	): string
	{
		$id = sprintf('%08d', random_int(1, 99_999_999));

		$this->w = new \XMLWriter;
		$this->w->openMemory();
		$this->w->startDocument('1.0', 'Windows-1250');

		// <dat:dataPack>
		$this->w->startElementNs('dat', 'dataPack', null);
		$this->w->writeAttribute('id', $id);
		$this->w->writeAttribute('ico', $this->ico);
		$this->w->writeAttribute('application', $this->application);
		$this->w->writeAttribute('version', '2.0');
		$this->w->writeAttribute('note', $note);

		foreach (self::Namespaces as $prefix => $uri) {
			$this->w->writeAttributeNs('xmlns', $prefix, null, $uri);
		}

		// <dat:dataPackItem>
		$this->w->startElementNs('dat', 'dataPackItem', null);
		$this->w->writeAttribute('id', $id);
		$this->w->writeAttribute('version', '2.0');

		// Root element (e.g. inv:invoice)
		[$prefix, $name] = explode(':', $rootElement);
		$this->w->startElementNs($prefix, $name, null);
		$this->w->writeAttribute('version', $version);
		foreach ($rootAttrs as $k => $v) {
			$this->w->writeAttribute($k, $v);
		}
		$this->writeData($data);
		$this->w->endElement();

		$this->w->endElement(); // dataPackItem
		$this->w->endElement(); // dataPack
		$this->w->endDocument();

		return $this->w->outputMemory();
	}


	/**
	 * Build dataPack XML from raw inner XML string (for sendRawXml).
	 */
	public function buildRaw(string $innerXml, string $note = ''): string
	{
		$id = sprintf('%08d', random_int(1, 99_999_999));
		return '<?xml version="1.0" encoding="Windows-1250"?>'
			. '<dat:dataPack'
			. ' xmlns:dat="http://www.stormware.cz/schema/version_2/data.xsd"'
			. ' xmlns:typ="http://www.stormware.cz/schema/version_2/type.xsd"'
			. ' id="' . $id . '"'
			. ' ico="' . htmlspecialchars($this->ico) . '"'
			. ' application="' . htmlspecialchars($this->application) . '"'
			. ' version="2.0"'
			. ' note="' . htmlspecialchars($note) . '">'
			. '<dat:dataPackItem id="' . $id . '" version="2.0">'
			. $innerXml
			. '</dat:dataPackItem>'
			. '</dat:dataPack>';
	}


	/**
	 * Recursively write nested data structure as XML elements.
	 * @param array<int|string, mixed> $data
	 */
	private function writeData(array $data): void
	{
		foreach ($data as $key => $value) {
			if ($value === null || $value === '') {
				continue;
			}

			// Numeric key = wrapper array, recurse
			if (is_int($key)) {
				if (is_array($value)) {
					$this->writeData($value);
				}
				continue;
			}

			[$prefix, $name] = explode(':', $key);
			$this->w->startElementNs($prefix, $name, null);

			if (is_array($value)) {
				// Check for '@attr' keys = attributes
				foreach ($value as $k => $v) {
					if (is_string($k) && str_starts_with($k, '@') && is_scalar($v)) {
						$this->w->writeAttribute(substr($k, 1), (string) $v);
					}
				}
				// Write child elements (skip @attr keys)
				$children = array_filter($value, fn($k) => !is_string($k) || !str_starts_with($k, '@'), ARRAY_FILTER_USE_KEY);
				if ($children) {
					$this->writeData($children);
				}
			} elseif ($value instanceof \DateTimeInterface) {
				$this->w->text($value->format('Y-m-d'));
			} elseif (is_bool($value)) {
				$this->w->text($value ? 'true' : 'false');
			} elseif (is_scalar($value)) {
				$this->w->text((string) $value);
			}

			$this->w->endElement();
		}
	}
}
