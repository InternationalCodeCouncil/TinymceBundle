<?php
namespace Stfalcon\Bundle\TinymceBundle\Twig\Extension;

use Stfalcon\Bundle\TinymceBundle\Helper\LocaleHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Twig Extension for TinyMce support.
 *
 * @author naydav <web@naydav.com>
 */
class StfalconTinymceExtension extends \Twig_Extension
{
    /**
     * @var ContainerInterface $container Container interface
     */
    protected $container;

    /**
     * Asset Base Url
     *
     * Used to over ride the asset base url (to not use CDN for instance)
     *
     * @var String
     */
    protected $baseUrl;

    /**
     * Initialize tinymce helper
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets a service.
     *
     * @param string $id The service identifier
     *
     * @return object The associated service
     */
    public function getService($id)
    {
        return $this->container->get($id);
    }

    /**
     * Get parameters from the service container
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->container->getParameter($name);
    }

    /**
     * Returns a list of functions to add to the existing list.
     *
     * @return array An array of functions
     */
    public function getFunctions()
    {
        return [
          new \Twig_Function('tinymce_init', 'tinymceInit', ['is_safe' => ['html']])
        ];
    }

    /**
     * TinyMce initializations
     *
     * @param array $options
     *
     * @return string
     */
    public function tinymceInit($options = array())
    {
        $config = $this->getParameter('stfalcon_tinymce.config');
        $config = array_merge_recursive($config, $options);

        $this->baseUrl = (!isset($config['base_url']) ? null : $config['base_url']);

        // Asset package name
        $assetPackageName = (!isset($config['asset_package_name']) ? null : $config['asset_package_name']);
        unset($config['asset_package_name']);

        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('assets.packages');

        // Get path to tinymce script for the jQuery version of the editor
        if ($config['tinymce_jquery']) {
            $config['jquery_script_url'] = $assets->getUrl(
                $this->baseUrl.'bundles/stfalcontinymce/vendor/tinymce/tinymce.jquery.min.js',
                $assetPackageName
            );
        }

        // If the language is not set in the config...
        if (!isset($config['language']) || empty($config['language'])) {
            // get it from the request
            $config['language'] = $this->container->get('request_stack')->getCurrentRequest()->getLocale();
        }

        $config['language'] = LocaleHelper::getLanguage($config['language']);

        $langDirectory = __DIR__.'/../../Resources/public/vendor/tinymce/langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory.$config['language'].'.js')) {
            unset($config['language']);
        }

        foreach($config['configuration'] as &$themeConfig){
            // Get local button's image
            foreach ($themeConfig['tinymce_buttons'] as &$customButton) {
                if ($customButton['image']) {
                    $customButton['image'] = $this->getAssetsUrl($customButton['image']);
                } else {
                    unset($customButton['image']);
                }

                if ($customButton['icon']) {
                    $customButton['icon'] = $this->getAssetsUrl($customButton['icon']);
                } else {
                    unset($customButton['icon']);
                }
            }

            // Update URL to external plugins
            foreach ($themeConfig['external_plugins'] as &$extPlugin) {
                $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
            }

            if (isset($config['language']) && $config['language']) {
                // TinyMCE does not allow to set different languages to each instance
                foreach ($themeConfig['theme'] as $themeName => $themeOptions) {
                    $themeConfig['theme'][$themeName]['language'] = $config['language'];
                }
            }

            if (isset($themeConfig['theme']) && $themeConfig['theme'])
            {
                // Parse the content_css of each theme so we can use 'asset[path/to/asset]' in there
                foreach ($themeConfig['theme'] as $themeName => $themeOptions) {
                     if (isset($themeOptions['content_css'])) {
                        // As there may be multiple CSS Files specified we need to parse each of them individually
                        $cssFiles = $themeOptions['content_css'];
                        if (!is_array($themeOptions['content_css'])) {
                            $cssFiles = explode(',', $themeOptions['content_css']);
                        }

                        foreach ($cssFiles as $idx => $file) {
                            $cssFiles[$idx] = $this->getAssetsUrl(trim($file)); // we trim to be sure we get the file without spaces.
                        }

                        // After parsing we add them together again.
                        $themeConfig['theme'][$themeName]['content_css'] = implode(',', $cssFiles);
                    }

                    // Parse spellchecker RPC url so we can use 'path[route_name]' in there
                    if (isset($themeOptions['spellchecker_rpc_url'])) {
                        $spellCheckerUrl = $this->getRouteUrl($themeOptions['spellchecker_rpc_url']);
                        $themeConfig['theme'][$themeName]['spellchecker_rpc_url'] = $spellCheckerUrl;
                    }
                }
            }
        }

        $tinymceConfiguration = preg_replace(
            array(
                '/"file_browser_callback":"([^"]+)"\s*/',
                '/"file_picker_callback":"([^"]+)"\s*/',
                '/"paste_preprocess":"([^"]+)"\s*/',
            ),
            array(
                'file_browser_callback:$1',
                'file_picker_callback:$1',
                '"paste_preprocess":$1',
            ),
            json_encode($config)
        );

        return $this->getService('templating')->render('StfalconTinymceBundle:Script:init.html.twig', array(
            'tinymce_config'     => $tinymceConfiguration,
            'include_jquery'     => $config['include_jquery'],
            'tinymce_jquery'     => $config['tinymce_jquery'],
            'asset_package_name' => $assetPackageName,
            'base_url'           => $this->baseUrl,
        ));
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'stfalcon_tinymce';
    }

    /**
     * Get url from config string
     *
     * @param string $inputUrl
     *
     * @return string
     */
    protected function getAssetsUrl($inputUrl)
    {
        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('assets.packages');

        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $assets->getUrl($this->baseUrl.$url);
        }

        return $inputUrl;
    }

    /**
     * Generate URL from route name
     *
     * @param string $inputUrl
     *
     * @return string
     */
    protected function getRouteUrl($inputUrl)
    {
        $routeName = preg_replace('/^path\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $routeName) {
            /* @var $router \Symfony\Component\Routing\RouterInterface */
            $router = $this->getService('router');

            $inputUrl = $router->generate($routeName);
        }

        return $inputUrl;
    }
}
