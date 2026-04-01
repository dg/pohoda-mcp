<?php declare(strict_types=1);

namespace DG\Pohoda;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Exception\ToolCallException;


/**
 * Decorates the SDK's ReferenceHandler to convert anything a tool throws into a ToolCallException,
 * which the SDK renders as a CallToolResult with isError=true so the model can self-correct.
 * Without this, an escaping \Throwable becomes an opaque JSON-RPC -32603 with no detail.
 *
 * Conversion rules (first match wins):
 *   - ToolCallException: rethrown unchanged.
 *   - InvalidArgumentException: clean caller-input message forwarded as-is.
 *   - any other \Throwable: wrapped with message + class + file:line so genuine bugs surface.
 */
final class McpToolCallGuard implements ReferenceHandlerInterface
{
	public function __construct(
		private readonly ReferenceHandlerInterface $handler,
	) {
	}


	/**
	 * @param array<string, mixed> $arguments
	 */
	public function handle(ElementReference $reference, array $arguments): mixed
	{
		try {
			return $this->handler->handle($reference, $arguments);
		} catch (ToolCallException $e) {
			throw $e;
		} catch (\InvalidArgumentException $e) {
			throw new ToolCallException($e->getMessage(), 0, $e);
		} catch (\Throwable $e) {
			throw new ToolCallException(sprintf('%s (%s in %s:%d)', $e->getMessage(), $e::class, basename($e->getFile()), $e->getLine()), 0, $e);
		}
	}
}
