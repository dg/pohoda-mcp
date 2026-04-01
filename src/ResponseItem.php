<?php declare(strict_types=1);

namespace DG\Pohoda;

use function count, is_array;


final class ResponseItem
{
	public readonly string $id;
	public readonly string $state;

	/** @var array<string, mixed> */
	public readonly array $data;


	public function __construct(\DOMElement $node)
	{
		$this->id = $node->getAttribute('id') ?: '';
		$this->state = $node->getAttribute('state') ?: 'unknown';

		$data = [];
		foreach ($node->childNodes as $child) {
			if ($child instanceof \DOMElement) {
				$data = self::domToArray($child);
				break;
			}
		}
		$this->data = $data;
	}


	public function isOk(): bool
	{
		return $this->state === 'ok';
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'state' => $this->state,
			'data' => $this->data,
		];
	}


	/**
	 * @return array<string, mixed>
	 */
	private static function domToArray(\DOMElement $element): array
	{
		$result = [];

		foreach ($element->attributes ?? [] as $attr) {
			$result['@' . $attr->localName] = $attr->value;
		}

		$children = [];
		$textContent = '';
		foreach ($element->childNodes as $child) {
			if ($child instanceof \DOMElement) {
				$children[] = $child;
			} elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
				$textContent .= $child->textContent;
			}
		}

		if (!$children) {
			if ($result) {
				if (trim($textContent) !== '') {
					$result['@value'] = trim($textContent);
				}
			} else {
				return ['@value' => trim($textContent)];
			}
			return $result;
		}

		foreach ($children as $child) {
			$name = (string) $child->localName;
			$childData = self::domToArray($child);

			if (count($childData) === 1 && isset($childData['@value'])) {
				$childData = $childData['@value'];
			}

			if (isset($result[$name])) {
				if (!is_array($result[$name]) || !isset($result[$name][0])) {
					$result[$name] = [$result[$name]];
				}
				$result[$name][] = $childData;
			} else {
				$result[$name] = $childData;
			}
		}

		return $result;
	}
}
