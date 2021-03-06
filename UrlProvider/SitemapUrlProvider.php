<?php

namespace Zenstruck\CacheBundle\UrlProvider;

use Symfony\Component\DomCrawler\Crawler;
use Zenstruck\CacheBundle\Http\Client;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class SitemapUrlProvider implements UrlProvider
{
    private $host;
    private $client;
    private $urls;

    /**
     * @param string $host
     * @param Client $client
     */
    public function __construct($host, Client $client)
    {
        if (!class_exists('Symfony\\Component\\DomCrawler\\Crawler') || !class_exists('Symfony\\Component\\CssSelector\\CssSelector')) {
            throw new \RuntimeException('symfony/dom-crawler and symfony/css-selector must be installed to use SitemapUrlProvider.');
        }

        $this->host = $host;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls()
    {
        if (is_array($this->urls)) {
            return $this->urls;
        }

        $urls = array();

        $sitemaps = $this->getSitemapEntries($this->addPathToHost('sitemap_index.xml'));

        if (count($sitemaps)) {
            // index found, loop through sitemaps
            foreach ($sitemaps as $sitemap) {
                $urls = array_merge($urls, $this->getSitemapEntries($sitemap));
            }
        } else {
            // no index, try sitemap.xml
            $urls = $this->getSitemapEntries($this->addPathToHost('sitemap.xml'));
        }

        return $this->urls = $urls;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->getUrls());
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function addPathToHost($path)
    {
        return sprintf('%s/%s', trim($this->host, '/'), ltrim($path, '/'));
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function getSitemapEntries($url)
    {
        $response = $this->client->fetch($url);

        if (200 !== $response->getStatusCode()) {
            return array();
        }

        $crawler = new Crawler($response->getContent());
        $ret = array();
        $filter = 'loc';

        // check for namespaces
        if (preg_match('/xmlns:/', $response->getContent())) {
            $filter = 'default|loc';
        }

        foreach ($crawler->filter($filter) as $node) {
            $ret[] = $node->nodeValue;
        }

        return $ret;
    }
}
