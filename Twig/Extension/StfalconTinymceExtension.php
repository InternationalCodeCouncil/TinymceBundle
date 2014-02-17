<?php
namespace Stfalcon\Bundle\TinymceBundle\Twig\Extension;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Twig Extension for TinyMce support.
 *
 * @author naydav <web@naydav.com>
 */
class StfalconTinymceExtension extends \Twig_Extension
{
    /**
     * Container
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Asset Base Url
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
        return array(
            'tinymce_init' => new \Twig_Function_Method($this, 'tinymceInit', array('is_safe' => array('html')))
        );
    }

    /**
     * TinyMce initializations
     *
     * @return string
     */
    public function tinymceInit()
    {
        $configuration = $this->getParameter('stfalcon_tinymce.config');

        $this->baseUrl = (!isset($config['base_url']) ? null : $config['base_url']);
        /** @var $assets \Symfony\Component\Templating\Helper\CoreAssetsHelper */
        $assets = $this->getService('templating.helper.assets');

        // Get path to tinymce script for the jQuery version of the editor
        if ($configuration['tinymce_jquery']) {
            $configuration['jquery_script_url'] = $assets->getUrl(
                $this->baseUrl . 'bundles/stfalcontinymce/vendor/tinymce/tinymce.jquery.min.js'
            );
        }

        // If the language is not set in the config...
        if (!isset($configuration['language']) || empty($configuration['language'])) {
            // get it from the request
            $configuration['language'] = $this->getService('request')->getLocale();
        }

        $langDirectory = __DIR__ . '/../../Resources/public/vendor/tinymce/langs/';

        // A language code coming from the locale may not match an existing language file
        if (!file_exists($langDirectory . $configuration['language'] . '.js')) {
            // Try shortening the code
            if (strlen($configuration['language']) > 2) {
                $shortCode = substr($configuration['language'], 0, 2);

                if (file_exists($langDirectory . $shortCode . '.js')) {
                    $configuration['language'] = $shortCode;
                } else {
                    unset($configuration['language']);
                }
            } else {
                // Try expanding the code
                $longCode = $configuration['language'] . '_' . strtoupper($configuration['language']);

                if (file_exists($langDirectory . $longCode . '.js')) {
                    $configuration['language'] = $longCode;
                } else {
                    unset($configuration['language']);
                }
            }
        }

        foreach($configuration['configuration'] as &$config){
            // Get local button's image
            foreach ($config['tinymce_buttons'] as &$customButton) {
                $customButton['image'] = $this->getAssetsUrl($customButton['image']);
            }

            // Update URL to external plugins
            foreach ($config['external_plugins'] as &$extPlugin) {
                $extPlugin['url'] = $this->getAssetsUrl($extPlugin['url']);
            }

            if (isset($configuration['language']) && $configuration['language']) {
                // TinyMCE does not allow to set different languages to each instance
                foreach ($config['theme'] as $themeName => $themeOptions) {
                    $config['theme'][$themeName]['language'] = $configuration['language'];
                }
            }

        }

        return $this->getService('templating')->render('StfalconTinymceBundle:Script:init.html.twig', array(
            'tinymce_config' => preg_replace('/"file_browser_callback":"([^"]+)"\s*/', 'file_browser_callback:$1', json_encode($configuration)),
            'include_jquery' => $configuration['include_jquery'],
            'tinymce_jquery' => $configuration['tinymce_jquery'],
            'base_url'       => $this->baseUrl
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
        $assets = $this->getService('templating.helper.assets');

        $url = preg_replace('/^asset\[(.+)\]$/i', '$1', $inputUrl);

        if ($inputUrl !== $url) {
            return $assets->getUrl($this->baseUrl . $url);
        }

        return $inputUrl;
    }
}