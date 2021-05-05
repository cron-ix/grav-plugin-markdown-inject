<?php
/**
 * MarkdownInject
 *
 * This plugin imports markdown files from given URLs
 *
 * Licensed under MIT, see LICENSE.
 */

namespace Grav\Plugin;

use Grav\Common\Config\Config;
use Grav\Common\Grav;
use Grav\Common\Helpers\Excerpts;
use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Page\Page;
use Grav\Common\Uri;
use Grav\Framework\Psr7\Response;
use Grav\Framework\RequestHandler\Exception\RequestException;
use Grav\Plugin\Admin\Admin;
use Psr\Http\Message\ResponseInterface;
use RocketTheme\Toolbox\Event\Event;

class MarkdownInjectPlugin extends Plugin
{
    /**
     * Return a list of subscribed events.
     *
     * @return array    The list of events of the plugin of the form
     *                      'name' => ['method_name', priority].
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize configuration.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->enable([
                'onAdminTaskExecute' => ['onAdminTaskExecute', 0],
            ]);
            return;
        }

        $this->enable([
            'onPageContentRaw' => ['onPageContentRaw', 0],
        ]);
    }

    /**
     * Add content after page content was read into the system.
     *
     * @param  Event  $event An event object, when `onPageContentRaw` is fired.
     */
    public function onPageContentRaw(Event $event)
    {
        /** @var Page $page */
        $page = $event['page'];

        /** @var Config $config */
        $config = $this->mergeConfig($page);


        if ($config->get('enabled')) {
            // Get raw content and substitute all formulas by a unique token
            $raw = $page->getRawContent();

            // build an anonymous function to pass to `parseLinks()`
            $function = function ($matches) use (&$page, &$twig, &$config) {

                $search = $matches[0]; // holds the string to be replaced, eg: [plugin:markdown-inject](https://domain.com/file.md)

                // load file into $inject
                // get the full URL to the markdown file from the search string
                if (preg_match('/https:\/\/(.*)?(\.md|download)/i', $search, $url)) { 
                    // URl found, load file with error suppressed
                    $file_content = @file_get_contents($url[0]);
                    // do the error handling
                    if ($file_content === FALSE) {
                        $inject = "error while loading from file"; // url or file not found
                    }
                    // no errors, file found and read
                    else {
                        $inject = $file_content;
                    }
                    $replace = $inject;
                } else {
                    // replace string is search string, if it not matches the URL pattern
                    $replace = $matches[0];
                }

                // do the replacement
                return str_replace($search, $replace, $search);
            };

            // set the parsed content back into as raw content
            $page->setRawContent($this->parseInjectLinks($raw, $function));
        }
    }

    protected function parseInjectLinks($content, $function)
    {
        $regex = '/\[plugin:markdown-inject\]\(https:\/\/(.*)?(\.md|download)\)/i';
        return preg_replace_callback($regex, $function, $content);
    }

}
