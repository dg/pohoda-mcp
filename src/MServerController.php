<?php declare(strict_types=1);

namespace DG\Pohoda;


/**
 * Controls the local Pohoda mServer process via `pohoda.exe /HTTP` commands.
 * Windows-only; mServer runs only as part of Pohoda, which is a Windows app.
 */
final class MServerController
{
	public function __construct(
		private readonly string $exePath,
		private readonly string $configName,
	) {
	}


	/**
	 * Launches the mServer (non-blocking) and polls `$client->getStatus()` once
	 * per second until it responds or the timeout elapses.
	 *
	 * @throws \RuntimeException on launch failure or when the mServer does not
	 *                           come up within $timeoutSeconds
	 */
	public function start(PohodaClient $client, int $timeoutSeconds = 30): void
	{
		$this->run('start');

		for ($i = 0; $i < $timeoutSeconds; $i++) {
			sleep(1);
			try {
				$client->getStatus();
				return;
			} catch (\RuntimeException) {
				// mServer not ready yet, keep polling
			}
		}

		throw new \RuntimeException(sprintf(
			"mServer '%s' did not start within %d seconds.",
			$this->configName,
			$timeoutSeconds,
		));
	}


	/**
	 * Sends a stop command to the mServer (non-blocking, fire-and-forget).
	 * Does not wait for the process to terminate.
	 *
	 * @throws \RuntimeException on launch failure of pohoda.exe
	 */
	public function stop(): void
	{
		$this->run('stop');
	}


	/**
	 * Runs `pohoda.exe /HTTP <cmd> "<configName>"` via Windows `start /B` so
	 * that PHP does not block on the child's stdout handle.
	 */
	private function run(string $httpCommand): void
	{
		$cmd = sprintf(
			'start "" /B "%s" /HTTP %s "%s"',
			$this->exePath,
			$httpCommand,
			$this->configName,
		);
		$pipe = popen($cmd, 'r') ?: throw new \RuntimeException('Failed to execute: ' . $cmd);
		pclose($pipe);
	}
}
