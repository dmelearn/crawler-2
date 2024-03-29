<?php

namespace Dmelearn\Crawler;

use Generator;
use Tree\Node\Node;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Link;
use Psr\Http\Message\ResponseInterface;
use Dmelearn\Crawler\CrawlQueue\CrawlQueue;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Dmelearn\Crawler\CrawlQueue\CollectionCrawlQueue;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;

class Crawler
{
    /** @var \GuzzleHttp\Client */
    protected $client;

    /** @var \Dmelearn\Crawler\Url */
    protected $baseUrl;

    /** @var \Dmelearn\Crawler\CrawlObserver */
    protected $crawlObserver;

    /** @var \Dmelearn\Crawler\CrawlProfile */
    protected $crawlProfile;

    /** @var int */
    protected $concurrency;

    /** @var \Dmelearn\Crawler\CrawlQueue\CrawlQueue */
    protected $crawlQueue;

    /** @var int */
    protected $crawledUrlCount = 0;

    /** @var int|null */
    protected $maximumCrawlCount = null;

    /** @var int|null */
    protected $maximumDepth = null;

    /** @var \Tree\Node\Node */
    protected $depthTree;

    /** @var bool */
    protected $executeJavaScript = false;

    protected static $defaultClientOptions = [
        RequestOptions::COOKIES => true,
        RequestOptions::CONNECT_TIMEOUT => 10,
        RequestOptions::TIMEOUT => 10,
        RequestOptions::ALLOW_REDIRECTS => false,
    ];

    public function __construct(Client $client, int $concurrency = 10)
    {
        $this->client = $client;

        $this->concurrency = $concurrency;

        $this->crawlProfile = new CrawlAllUrls();

        $this->crawlQueue = new CollectionCrawlQueue();
    }

    /**
     * Create a Crawl that first starts with a login post to a url and then keeps the cookie for all further crawls.
     *
     * @param string $loginPostUrl
     * @param array $formParams
     * @param array $clientOptions
     * @return static
     */
    public static function createLoggedIn(string $loginPostUrl, array $formParams, array $clientOptions = [])
    {
        $cookieJar = new \GuzzleHttp\Cookie\CookieJar();

        $clientOptions = (count($clientOptions))
            ? $clientOptions
            : self::$defaultClientOptions;

        $clientOptions[RequestOptions::COOKIES] = $cookieJar;

        $client = new Client($clientOptions);

        $client->post($loginPostUrl, [
                'form_params' => $formParams,
                'cookies' => $cookieJar
            ]
        );

        return new static($client);
    }

    /**
     * Create a regular crawl
     *
     * @param array $clientOptions
     * @return static
     */
    public static function create(array $clientOptions = [])
    {
        $clientOptions = (count($clientOptions))
            ? $clientOptions
            : self::$defaultClientOptions;

        $client = new Client($clientOptions);

        return new static($client);
    }

    /**
     * @param int $concurrency
     *
     * @return $this
     */
    public function setConcurrency(int $concurrency)
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    /**
     * @param int $maximumCrawlCount
     *
     * @return $this
     */
    public function setMaximumCrawlCount(int $maximumCrawlCount)
    {
        $this->maximumCrawlCount = $maximumCrawlCount;

        return $this;
    }

    /**
     * @param int $maximumDepth
     *
     * @return $this
     */
    public function setMaximumDepth(int $maximumDepth)
    {
        $this->maximumDepth = $maximumDepth;

        return $this;
    }

    /**
     * @param CrawlQueue $crawlQueue
     * @return $this
     */
    public function setCrawlQueue(CrawlQueue $crawlQueue)
    {
        $this->crawlQueue = $crawlQueue;

        return $this;
    }

    /**
     * @return $this
     */
    public function executeJavaScript()
    {
        $this->executeJavaScript = true;

        return $this;
    }

    /**
     * @return $this
     */
    public function doNotExecuteJavaScript()
    {
        $this->executeJavaScript = false;

        return $this;
    }

    /**
     * @param \Dmelearn\Crawler\CrawlObserver $crawlObserver
     *
     * @return $this
     */
    public function setCrawlObserver(CrawlObserver $crawlObserver)
    {
        $this->crawlObserver = $crawlObserver;

        return $this;
    }

    /**
     * @param \Dmelearn\Crawler\CrawlProfile $crawlProfile
     *
     * @return $this
     */
    public function setCrawlProfile(CrawlProfile $crawlProfile)
    {
        $this->crawlProfile = $crawlProfile;

        return $this;
    }

    /**
     * @param \Dmelearn\Crawler\Url|string $baseUrl
     * @return mixed
     */
    public function startCrawling($baseUrl)
    {
        if (! $baseUrl instanceof Url) {
            $baseUrl = Url::create($baseUrl);
        }

        $this->baseUrl = $baseUrl;

        $crawlUrl = CrawlUrl::create($baseUrl);

        $this->addToCrawlQueue($crawlUrl);

        $this->depthTree = new Node((string) $this->baseUrl);

        $this->startCrawlingQueue();

        return $this->crawlObserver->finishedCrawling();
    }

    protected function startCrawlingQueue()
    {
        while ($this->crawlQueue->hasPendingUrls()) {
            $pool = new Pool($this->client, $this->getCrawlRequests(), [
                'concurrency' => $this->concurrency,
                'options' => $this->client->getConfig(),
                'fulfilled' => function (ResponseInterface $response, int $index) {
                    $crawlUrl = $this->crawlQueue->getUrlById($index);
                    $this->handleResponse($response, $crawlUrl);

                    if (! $this->crawlProfile instanceof CrawlSubdomains) {
                        if ($crawlUrl->url->host !== $this->baseUrl->host) {
                            return;
                        }
                    }

                    $this->addAllLinksToCrawlQueue(
                        (string) $response->getBody(),
                        $crawlUrl->url
                    );
                },
                'rejected' => function ($exception, int $index) {
                    if ($exception instanceof ConnectException) {
                        $exception = new RequestException('', $exception->getRequest());
                    }
                    if ($exception instanceof RequestException) {
                        $this->handleResponse(
                            $exception->getResponse(),
                            $this->crawlQueue->getUrlById($index),
                            $exception->getMessage() ?? null
                        );
                    }
                    usleep(100);
                },
            ]);

            $promise = $pool->promise();
            $promise->wait();
        }
    }

    /**
     * @param ResponseInterface|null $response
     * @param CrawlUrl $crawlUrl
     */
    protected function handleResponse($response, CrawlUrl $crawlUrl, $error = null)
    {
        $this->crawlObserver->hasBeenCrawled($crawlUrl->url, $response, $crawlUrl->foundOnUrl, $error);
    }

    protected function getCrawlRequests(): Generator
    {
        while ($crawlUrl = $this->crawlQueue->getFirstPendingUrl()) {
            if (! $this->crawlProfile->shouldCrawl($crawlUrl->url)) {
                continue;
            }

            if ($this->crawlQueue->hasAlreadyBeenProcessed($crawlUrl)) {
                continue;
            }

            $this->crawlObserver->willCrawl($crawlUrl->url);

            $this->crawlQueue->markAsProcessed($crawlUrl);

            yield $crawlUrl->getId() => new Request('GET', (string) $crawlUrl->url);
        }
    }

    protected function addAllLinksToCrawlQueue(string $html, Url $foundOnUrl)
    {
        $allLinks = $this->extractAllLinks($html, $foundOnUrl);

        collect($allLinks)
            ->filter(function (Url $url) {
                return $url->hasCrawlableScheme();
            })
            ->map(function (Url $url) {
                return $this->normalizeUrl($url);
            })
            ->filter(function (Url $url) {
                return $this->crawlProfile->shouldCrawl($url);
            })
            ->reject(function ($url) {
                return $this->crawlQueue->has($url);
            })
            ->each(function (Url $url) use ($foundOnUrl) {
                $node = $this->addToDepthTree($this->depthTree, (string) $url, $foundOnUrl);

                if (! $this->shouldCrawl($node)) {
                    return;
                }

                if ($this->maximumCrawlCountReached()) {
                    return;
                }

                $crawlUrl = CrawlUrl::create($url, $foundOnUrl);

                $this->addToCrawlQueue($crawlUrl);
            });
    }

    protected function shouldCrawl(Node $node): bool
    {
        if (is_null($this->maximumDepth)) {
            return true;
        }

        return $node->getDepth() <= $this->maximumDepth;
    }

    protected function extractAllLinks(string $html, Url $foundOnUrl): Collection
    {
        if ($this->executeJavaScript) {
            $html = $this->getBodyAfterExecutingJavaScript($foundOnUrl);
        }

        $domCrawler = new DomCrawler($html, $foundOnUrl);

        return collect($domCrawler->filterXpath('//a')->links())
            ->map(function (Link $link) {
                return Url::create($link->getUri());
            });
    }

    protected function normalizeUrl(Url $url): Url
    {
        return $url->removeFragment();
    }

    protected function addToDepthTree(Node $node, string $url, string $parentUrl)
    {
        $returnNode = null;

        if ($node->getValue() === $parentUrl) {
            $newNode = new Node($url);

            $node->addChild($newNode);

            return $newNode;
        }

        foreach ($node->getChildren() as $currentNode) {
            $returnNode = $this->addToDepthTree($currentNode, $url, $parentUrl);

            if ( ! is_null($returnNode)) {
                break;
            }
        }

        return $returnNode;
    }

    protected function getBodyAfterExecutingJavaScript(Url $foundOnUrl): string
    {
        $browsershot = Browsershot::url((string) $foundOnUrl);

        if ($this->pathToChromeBinary) {
            $browsershot->setChromePath($this->pathToChromeBinary);
        }

        $html = $browsershot->bodyHtml();

        return html_entity_decode($html);
    }

    protected function addToCrawlQueue(CrawlUrl $crawlUrl)
    {
        $this->crawledUrlCount++;

        $this->crawlQueue->add($crawlUrl);

        return $this;
    }

    protected function maximumCrawlCountReached(): bool
    {
        if (is_null($this->maximumCrawlCount)) {
            return false;
        }

        return $this->crawledUrlCount >= $this->maximumCrawlCount;
    }
}
