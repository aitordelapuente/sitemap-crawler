<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['url'])) {
    echo json_encode(['error' => 'Missing URL parameter']);
    exit;
}

$startUrl = $_GET['url'];

class SitemapCrawler {
    private $urls = [];
    private $visitedSitemaps = [];
    private $errors = [];

    public function crawl($url) {
        $this->processSitemap($url);
        return [
            'urls' => $this->urls,
            'count' => count($this->urls),
            'errors' => $this->errors
        ];
    }

    private function processSitemap($url) {
        if (in_array($url, $this->visitedSitemaps)) {
            return;
        }
        $this->visitedSitemaps[] = $url;

        $content = $this->fetchUrl($url);
        if (!$content) {
            return;
        }

        // Suppress XML parsing errors
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);

        if ($xml === false) {
            $this->errors[] = "Failed to parse XML from: $url";
            return;
        }

        // Check for Sitemap Index (nested sitemaps)
        if (isset($xml->sitemap)) {
            foreach ($xml->sitemap as $sitemap) {
                $loc = (string)$sitemap->loc;
                if ($loc) {
                    $this->processSitemap($loc);
                }
            }
        }
        // Check for URL Set (final URLs)
        elseif (isset($xml->url)) {
            foreach ($xml->url as $urlItem) {
                $loc = (string)$urlItem->loc;
                if ($loc) {
                    $this->urls[] = $loc;
                }
            }
        }
    }

    private function fetchUrl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SitemapCrawler/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $this->errors[] = "Curl error fetching $url: " . curl_error($ch);
            curl_close($ch);
            return false;
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->errors[] = "HTTP $httpCode fetching $url";
            return false;
        }

        return $data;
    }
}

$crawler = new SitemapCrawler();
$result = $crawler->crawl($startUrl);

echo json_encode($result);
