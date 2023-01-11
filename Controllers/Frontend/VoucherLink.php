<?php
class Shopware_Controllers_Frontend_Gutschein extends Enlight_Controller_Action {

    public function indexAction() {
        $snippets = Shopware()->Container()->get('snippets')->getNamespace('frontend/plugins/nnwebVoucherLink');
        
        $message = $snippets->get('needed', 'Bitte geben Sie einen Gutscheincode an.');
        
		Shopware()->Session()->nnwebVoucherLinkMessage = $message;
		Shopware()->Session()->nnwebVoucherLinkMessageType = 'error';
		Shopware()->Session()->nnwebVoucherLinkCode = '';
		
    	$this->forward('index', 'index');
    }

    public function __call($methodName, $params = null) {
    	if ($_GET["controller"] == "gutschein") {
			Shopware()->Session()->nnwebVoucherLinkCode = $_GET["action"];
			Shopware()->Session()->nnwebVoucherLinkFromController = true;
			$this->response->setHeader('Cache-Control', 'private', true);
    	}
    	
    	if (!empty($_GET["r"])) {
    		header("Location: " . $_GET["r"]);
			exit();
    	} else {
			$this->forward('index', 'index');
		}
    }
}