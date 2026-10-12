<?php declare(strict_types=1);

namespace DG\Pohoda;


final readonly class Response
{
	/** @var list<ResponseItem> */
	public array $items;
	public string $state;
	public string $programVersion;


	public function __construct(string $xml)
	{
		$doc = new \DOMDocument;
		$root = @$doc->loadXML($xml) ? $doc->documentElement : null; // @ invalid XML triggers a warning
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
