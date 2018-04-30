<?php
namespace OpenEuropa\pcas\Cas\Protocol;

use Http\Discovery\UriFactoryDiscovery;
use Http\Message\UriFactory;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AbstractCasProtocol.
 */
abstract class AbstractCasProtocol implements CasProtocolInterface, ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * The URI factory.
     *
     * @var \Http\Message\UriFactory
     */
    protected $uriFactory;

    /**
     * AbstractCasProtocol constructor.
     *
     * @param \Http\Message\UriFactory|NULL $uriFactory
     *   The URI factory.
     */
    public function __construct(UriFactory $uriFactory = null)
    {
        $this->uriFactory = is_null($uriFactory) ? UriFactoryDiscovery::find() : $uriFactory;
    }

    /**
     * Get the container.
     *
     * @return \Psr\Container\ContainerInterface
     *   The container.
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function getProperties()
    {
        return $this->getContainer()->getParameterBag()->all();
    }

    /**
     * {@inheritdoc}
     */
    public function currentUrl($url = '')
    {
        if (empty($url)) {
            $request = Request::createFromGlobals();
            $request->getQueryString();

            $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
        }

        $uri = $this->uriFactory->createUri($url);

        // Remove the ticket parameter if any.
        parse_str($uri->getQuery(), $query);
        unset($query['ticket']);

        return $uri->withQuery(http_build_query($query));
    }

    /**
     * {@inheritdoc}
     */
    public function get($name, array $query = [])
    {
        $properties = $this->getProperties();
        $name = strtolower($name);

        $query += $properties['protocol'][$name]['query'];
        $query += ['service' => ''];
        $query['service'] = $this->currentUrl($query['service'])->__toString();
        $query += (array) $this->getContainer()->get('pcas.session')->get('pcas/query');

        // Make sure that every query parameters is a string.
        $query = array_map(function ($value) {
            if (is_array($value)) {
                $value = implode(
                    ',',
                    iterator_to_array(
                        new \RecursiveIteratorIterator(
                            new \RecursiveArrayIterator($value)
                        )
                    )
                );
            }

            return $value;
        }, $query);

        // Remove parameters that are not allowed.
        $query = array_intersect_key(
            $query,
            array_combine(
                $properties['protocol'][$name]['allowed_parameters'],
                $properties['protocol'][$name]['allowed_parameters']
            )
        );

        return $this->uriFactory->createUri($properties['protocol'][$name]['uri'])
          ->withQuery(http_build_query($query));
    }
}
