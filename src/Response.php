<?php declare(strict_types=1);

namespace DG\Pohoda;


final class Response
{
	/** @var list<ResponseItem> */
	public readonly array $items;
	public readonly string $state;
	public readonly string $programVersion;


	public function __construct(string $xml)
	{
		$doc = new \DOMDocument;
		$root = @$doc->loadXML($xml) ? $doc->documentElement : null;
		$this->state = $root?->getAttribute('state') ?: ($root ? 'unknown' : 'error');
		$this->programVersion = $root?->getAttribute('programVersion') ?: '';

		$items = [];
		foreach ($root ? $root->childNodes : [] as $node) {
			if ($node instanceof \DOMElement) {
				$items[] = new ResponseItem($node);
			}
		}
		$this->items = $items;
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
			'state' => $this->state,
			'programVersion' => $this->programVersion,
			'items' => array_map(fn(ResponseItem $item) => $item->toArray(), $this->items),
		];
	}
}
