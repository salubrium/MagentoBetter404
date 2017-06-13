<?php
class Pulsestorm_Better404_Model_Observer
{

    public function tryNoCategoryUrl($observer)
    {
        if(!$this->_is404())
        {
            return;
        }
    
        $currentUrl = Mage::helper('core/url')->getCurrentUrl();
	//Freshdesk adds a unicode space to end of URL's at times - edge case but they haven't fixed it for two years
	$currentUrl = str_replace("%C2%A0", "", $currentUrl);

        $url = Mage::getSingleton('core/url')->parseUrl($currentUrl);
        $path = $url->getPath();
        $params = Mage::app()->getRequest()->getParams();

        $explodedPath = end(explode('/', $path));
        $lookupPath = explode('.', $explodedPath);

        $lookupPath = $lookupPath[0]; 

        //There must be a better way to do below and above.
        $rewriteUrl = $this->loadByRequestPath(
                new Varien_Object(), $lookupPath . Mage::helper('catalog/product')->getProductUrlSuffix(), Mage::app()->getStore()->getStoreId()
            );


        if($rewriteUrl->getTargetPath() == '')
        {
            return;
        }

        $productId = end(explode('/', $rewriteUrl->getTargetPath()));
        

        if(Mage::getModel('catalog/product')->load($productId)->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        {

            $redirectWithParams =  Mage::getUrl('',
            array(
                    '_direct' => $explodedPath,
                    '_query' => $params
                )
            );

            $response = Mage::app()->getResponse()->clearHeaders()
                ->setRedirect($redirectWithParams, 302)
                ->sendResponse();
            return $response;
        } else {
            return;
        }
    }

    protected function loadByRequestPath(Varien_Object $object, $requestPath, $storeId)
    {
        $resource = Mage::getSingleton('core/resource')->getConnection('core_read');
        $select = $resource->select()
            ->from(array('url_rewrite' => $resource->getTableName('core_url_rewrite')),
                array('request_path', 'target_path', 'url_rewrite_id', 'is_system')
            )
            ->where('url_rewrite.request_path = ' . $resource->quote($requestPath))
            ->where('url_rewrite.store_id = ' . $storeId . ' OR url_rewrite.store_id = 0')
            ->order('url_rewrite.store_id ' . Varien_Db_Select::SQL_DESC);

        $result = $resource->fetchRow($select);

        if (!empty($result)) 
        {
            $object->setData($result);
        }

        return $object;
    }
    
    protected function _is404()
    {
        $headers = Mage::app()->getResponse()->getHeaders();        
        foreach($headers as $header)
        {
            if(strToLower($header['name']) != 'status')
            {
                continue;
            }

            if(strpos($header['value'],'404') !== false)
            {
                return true;
            }
        }
        return false;
    }
}
