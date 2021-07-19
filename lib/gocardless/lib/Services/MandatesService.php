<?php
/**
 * WARNING: Do not edit by hand, this file was generated by Crank:
 *
 * https://github.com/gocardless/crank
 */

namespace GoCardlessPro\Services;

use \GoCardlessPro\Core\Paginator;
use \GoCardlessPro\Core\Util;
use \GoCardlessPro\Core\ListResponse;
use \GoCardlessPro\Resources\Mandate;
use \GoCardlessPro\Core\Exception\InvalidStateException;


/**
 * Service that provides access to the Mandate
 * endpoints of the API
 */
class MandatesService extends BaseService
{

    protected $envelope_key = 'mandates';
    protected $resource_class = '\GoCardlessPro\Resources\Mandate';


    /**
    * Create a mandate
    *
    * Example URL: /mandates
    *
    * @param  string[mixed] $params An associative array for any params
    * @return Mandate
    **/
    public function create($params = array())
    {
        $path = "/mandates";
        if(isset($params['params'])) { 
            $params['body'] = json_encode(array($this->envelope_key => (object)$params['params']));
        
            unset($params['params']);
        }

        
        try {
            $response = $this->api_client->post($path, $params);
        } catch(InvalidStateException $e) {
            if ($e->isIdempotentCreationConflict()) {
                return $this->get($e->getConflictingResourceId());
            }

            throw $e;
        }
        

        return $this->getResourceForResponse($response);
    }

    /**
    * List mandates
    *
    * Example URL: /mandates
    *
    * @param  string[mixed] $params An associative array for any params
    * @return ListResponse
    **/
    protected function _doList($params = array())
    {
        $path = "/mandates";
        if(isset($params['params'])) { $params['query'] = $params['params'];
            unset($params['params']);
        }

        
        $response = $this->api_client->get($path, $params);
        

        return $this->getResourceForResponse($response);
    }

    /**
    * Get a single mandate
    *
    * Example URL: /mandates/:identity
    *
    * @param  string        $identity Unique identifier, beginning with "MD".
    * @param  string[mixed] $params   An associative array for any params
    * @return Mandate
    **/
    public function get($identity, $params = array())
    {
        $path = Util::subUrl(
            '/mandates/:identity',
            array(
                
                'identity' => $identity
            )
        );
        if(isset($params['params'])) { $params['query'] = $params['params'];
            unset($params['params']);
        }

        
        $response = $this->api_client->get($path, $params);
        

        return $this->getResourceForResponse($response);
    }

    /**
    * Update a mandate
    *
    * Example URL: /mandates/:identity
    *
    * @param  string        $identity Unique identifier, beginning with "MD".
    * @param  string[mixed] $params   An associative array for any params
    * @return Mandate
    **/
    public function update($identity, $params = array())
    {
        $path = Util::subUrl(
            '/mandates/:identity',
            array(
                
                'identity' => $identity
            )
        );
        if(isset($params['params'])) { 
            $params['body'] = json_encode(array($this->envelope_key => (object)$params['params']));
        
            unset($params['params']);
        }

        
        $response = $this->api_client->put($path, $params);
        

        return $this->getResourceForResponse($response);
    }

    /**
    * Cancel a mandate
    *
    * Example URL: /mandates/:identity/actions/cancel
    *
    * @param  string        $identity Unique identifier, beginning with "MD".
    * @param  string[mixed] $params   An associative array for any params
    * @return Mandate
    **/
    public function cancel($identity, $params = array())
    {
        $path = Util::subUrl(
            '/mandates/:identity/actions/cancel',
            array(
                
                'identity' => $identity
            )
        );
        if(isset($params['params'])) { 
            $params['body'] = json_encode(array("data" => (object)$params['params']));
        
            unset($params['params']);
        }

        
        try {
            $response = $this->api_client->post($path, $params);
        } catch(InvalidStateException $e) {
            if ($e->isIdempotentCreationConflict()) {
                return $this->get($e->getConflictingResourceId());
            }

            throw $e;
        }
        

        return $this->getResourceForResponse($response);
    }

    /**
    * Reinstate a mandate
    *
    * Example URL: /mandates/:identity/actions/reinstate
    *
    * @param  string        $identity Unique identifier, beginning with "MD".
    * @param  string[mixed] $params   An associative array for any params
    * @return Mandate
    **/
    public function reinstate($identity, $params = array())
    {
        $path = Util::subUrl(
            '/mandates/:identity/actions/reinstate',
            array(
                
                'identity' => $identity
            )
        );
        if(isset($params['params'])) { 
            $params['body'] = json_encode(array("data" => (object)$params['params']));
        
            unset($params['params']);
        }

        
        try {
            $response = $this->api_client->post($path, $params);
        } catch(InvalidStateException $e) {
            if ($e->isIdempotentCreationConflict()) {
                return $this->get($e->getConflictingResourceId());
            }

            throw $e;
        }
        

        return $this->getResourceForResponse($response);
    }

    /**
    * List mandates
    *
    * Example URL: /mandates
    *
    * @param  string[mixed] $params
    * @return Paginator
    **/
    public function all($params = array())
    {
        return new Paginator($this, $params);
    }

}
