<?php

namespace Pilipinews\Website\Abscbn;

use Pilipinews\Common\Article;
use Pilipinews\Common\Crawler as DomCrawler;
use Pilipinews\Common\Interfaces\ScraperInterface;
use Pilipinews\Common\Scraper as AbstractScraper;

/**
 * ABS-CBN News Scraper
 *
 * @package Pilipinews
 * @author  Rougin Gutib <rougingutib@gmail.com>
 */
class Scraper extends AbstractScraper implements ScraperInterface
{
    /**
     * @var string[]
     */
    protected $removables = array('.patrolbox', '.op-related-articles', 'script', '.iwantbar');

    /**
     * @var string[]
     */
    protected $texts = array(
        'I-refresh ang pahinang ito para sa updates.',
        'I-refresh ang page na ito para sa updates.',
        'Refresh this link for more details.',
        'I-refresh ang web page na ito para sa mga pinakahuling update.'
    );

    /**
     * Returns the contents of an article.
     *
     * @param  string $link
     * @return \Pilipinews\Common\Article
     */
    public function scrape($link)
    {
        $this->prepare(mb_strtolower($link));

        $title = $this->title('h1.news-title');

        $this->remove((array) $this->removables);

        $body = $this->body('.article-content');

        $body = $this->album($body);

        $body = $this->embedly($body);

        $body = $this->image($body);

        $body = $this->tweet($body);

        $body = $this->video($body);

        $body = $this->post($body);

        $html = $this->html($body, $this->texts);

        return new Article($title, $html, $link);
    }

    /**
     * Converts an album element into a readable string.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function album(DomCrawler $crawler)
    {
        $callback = function (DomCrawler $crawler, $html)
        {
            $results = array();

            $pattern = '.slider-for > div > img';

            $items = $crawler->filter($pattern);

            $pattern = '.slider-desc > .item-desc > p';

            $texts = $crawler->filter($pattern);

            for ($i = 0; $i < $items->count(); $i++)
            {
                $link = 'PHOTO: ' . $items->eq($i)->attr('src');

                $text = '';

                if ($texts->count() !== 1)
                {
                    $text = $texts->eq($i)->text();
                }

                $result = '<p>' . $link . ' - ' . $text . '</p>';

                $results[] = str_replace(' - </', '</', $result);
            }

            return implode("\n\n", (array) $results);
        };

        return $this->replace($crawler, '.media-content', $callback);
    }

    /**
     * Converts an embedly elements into a readable string.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function embedly(DomCrawler $crawler)
    {
        $callback = function (DomCrawler $crawler)
        {
            $item = $crawler->filter('a')->first();

            return 'EMBED: ' . $item->attr('href');
        };

        return $this->replace($crawler, '.embedly-card', $callback);
    }

    /**
     * Converts image elements into a readable string.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function image(DomCrawler $crawler)
    {
        $callback = function (DomCrawler $crawler, $html)
        {
            $image = 'PHOTO: ' . $crawler->filter('img')->attr('src');

            $image = str_replace('?ext=.jpg', '', (string) $image);

            $text = '<p>' . $image . ' - ' . $crawler->text() . '</p>';

            if (strpos($html, '<em>') !== false)
            {
                $em = $crawler->filter('em')->first()->text();

                $text = str_replace($em, '(' . $em . ')', $text);
            }

            return str_replace(' -   </', '</', (string) $text);
        };

        return $this->replace($crawler, '.embed-wrap', $callback);
    }

    /**
     * Converts post elements into a readable string.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function post(DomCrawler $crawler)
    {
        $callback = function (DomCrawler $node, $html)
        {
            return '<p>POST: ' . $node->attr('data-href') . '</p>';
        };

        return $this->replace($crawler, '.fb-post', $callback);
    }

    /**
     * Converts video elements into a readable string.
     *
     * @param  \Symfony\Component\DomCrawler\Crawler $crawler
     * @return \Symfony\Component\DomCrawler\Crawler
     */
    protected function video(DomCrawler $crawler)
    {
        $callback = function (DomCrawler $crawler)
        {
            $element = $crawler->filter('iframe');

            $link = $element->attr('src');

            return '<p>VIDEO: ' . $link . '</p>';
        };

        return $this->replace($crawler, '.op-interactive', $callback);
    }
}
