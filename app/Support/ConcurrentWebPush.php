<?php

namespace App\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

/**
 * minishlink/web-push's flush() sends one request at a time, and its concurrent
 * flushPooled() needs an HTTPlug async client this app doesn't install. Sent
 * one by one, a few hundred pushes a minute is the ceiling — a keynote that
 * ten thousand people bookmarked would deliver its "starting soon" reminders
 * long after it had started. This sends each batch through Guzzle's own Pool
 * instead (Guzzle is already here for Laravel's HTTP client), a few dozen
 * requests in flight at once, and hands back the same MessageSentReports.
 *
 * Everything else — encryption, VAPID signing, the TTL/urgency headers, how a
 * report reads a 404/410 — is the library's own.
 */
class ConcurrentWebPush extends WebPush
{
    private Client $http;

    public function __construct(array $auth = [], array $defaultOptions = [], ?Client $http = null, private readonly int $concurrency = 25)
    {
        $http ??= new Client(['timeout' => 10, 'connect_timeout' => 5]);

        parent::__construct($auth, $defaultOptions, $http);

        $this->http = $http;
    }

    /**
     * @return \Generator<\Minishlink\WebPush\MessageSentReport>
     */
    public function flush(?int $batchSize = null): \Generator
    {
        if (empty($this->notifications)) {
            return;
        }

        $batchSize ??= $this->defaultOptions['batchSize'];
        $batches = array_chunk($this->notifications, $batchSize);
        $this->notifications = [];

        foreach ($batches as $batch) {
            $requests = [];
            $reports = [];

            // Each push is encrypted on its own, so one subscription with malformed
            // keys becomes a failed report — it can't take the whole batch down.
            foreach ($batch as $notification) {
                try {
                    foreach ($this->prepare([$notification]) as $request) {
                        $requests[] = $request;
                    }
                } catch (\Throwable $e) {
                    $endpoint = $notification->getSubscription()->getEndpoint();
                    $reports[] = new MessageSentReport(new Request('POST', $endpoint), null, false, $e->getMessage());
                }
            }

            $pool = new Pool($this->http, $requests, [
                'concurrency' => $this->concurrency,
                // A 404/410 ("this subscription is gone") is an answer to read, not an exception.
                'options' => ['http_errors' => false],
                'fulfilled' => function ($response, $index) use ($requests, &$reports): void {
                    $reports[] = $this->createReport($requests[$index], $response);
                },
                'rejected' => function ($reason, $index) use ($requests, &$reports): void {
                    $reports[] = $reason instanceof RequestException && $reason->hasResponse()
                        ? $this->createReport($requests[$index], $reason->getResponse())
                        : $this->createRejectedReport($requests[$index], $reason instanceof \Throwable ? $reason : new \RuntimeException((string) $reason));
                },
            ]);
            $pool->promise()->wait();

            yield from $reports;
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders = [];
        }
    }
}
