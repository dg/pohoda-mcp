<?php declare(strict_types=1);

namespace DG\Pohoda;


final readonly class Response
{
	/** @var list<ResponseItem> */
	public array $items;
	public string $state;
	public string $note;
	public string $programVersion;


	public function __construct(string $xml)
	{
		$doc = new \DOMDocument;
		$root = @$doc->loadXML($xml) ? $doc->documentElement : null; // @ invalid XML triggers a warning
		$this->state = $root?->getAttribute('state') ?: ($root ? 'unknown' : 'error');
		$this->note = $root?->getAttribute('note')
			?? 'The mServer response is not readable XML. The request may still have been processed, check the result in Pohoda before repeating it.';
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
		return $this->state === 'ok' && array_all($this->items, fn(ResponseItem $item) => $item->isOk());
	}


	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'state' => $this->state,
			'note' => $this->note,
			'programVersion' => $this->programVersion,
			'items' => array_map(fn(ResponseItem $item) => $item->toArray(), $this->items),
		];
	}
}
