<?php

namespace nnwebVoucherLink;

use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;

class nnwebVoucherLink extends \Shopware\Components\Plugin {
	private $config;

	public static function getSubscribedEvents() {
		return [
				'Enlight_Controller_Action_PreDispatch_Frontend_Checkout' => 'onPreDispatchCheckout',
				'Enlight_Controller_Action_PostDispatchSecure' => 'onPostDispatch',
				'Enlight_Controller_Dispatcher_ControllerPath_Frontend_Gutschein' => 'getFrontendVoucherLinkController'
		];
	}

	public function activate(ActivateContext $context) {
		$context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
		parent::activate($context);
	}

	public function deactivate(DeactivateContext $context) {
		$context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
		parent::deactivate($context);
	}

	public function update(UpdateContext $context) {
		$context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
		parent::update($context);
	}

	public function getFrontendVoucherLinkController() {
		return $this->getPath() . '/Controllers/Frontend/VoucherLink.php';
	}

	public function onPostDispatch(\Enlight_Controller_ActionEventArgs $args) {
		$this->config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName($this->getName());
		
		$controller = $args->getSubject();
		$request = $controller->Request();
		$response = $controller->Response();
		$view = $controller->View();
		
		if (!$request->isDispatched() || $response->isException() || !$view->hasTemplate() || $request->getModuleName() != 'frontend') {
			return;
		}
		
		if (!empty(Shopware()->Session()->nnwebVoucherLinkFromController)) {
			$voucherCode = Shopware()->Session()->nnwebVoucherLinkCode;
			Shopware()->Session()->nnwebVoucherLinkFromController = false;
		} else {
			$voucherCode = $request->getParam('gutschein');
		}
		
		$this->addVoucherCode($view, $voucherCode);
		
		// Falls ich über den Controller gekommen bin
		if (($request->getControllerName() == 'index' && $request->getActionName() == 'index')) {
			$message = Shopware()->Session()->nnwebVoucherLinkMessage;
			$type = Shopware()->Session()->nnwebVoucherLinkMessageType;
			if (!empty($message)) {
				$view->assign('nnwebVoucherLinkMessage', $message);
				$view->assign('nnwebVoucherLinkMessageType', $type);
				Shopware()->Session()->nnwebVoucherLinkMessage = '';
				Shopware()->Session()->nnwebVoucherLinkMessageType = '';
			}
		}
		
		$this->container->get('template')->addTemplateDir($this->getPath() . '/Resources/views/');
	}

	public function onPreDispatchCheckout(\Enlight_Controller_ActionEventArgs $args) {
		$this->config = $this->container->get('shopware.plugin.cached_config_reader')->getByPluginName($this->getName());
		
		$controller = $args->getSubject();
		$request = $controller->Request();
		$view = $controller->View();
		if ($request->getActionName() != 'cart' && $request->getActionName() != 'confirm') {
			return;
		}
		
		$voucherCode = Shopware()->Session()->nnwebVoucherLinkCode;
		$this->addVoucherCode($view, $voucherCode, true);
	}

	private function addVoucherCode($view, $voucherCode, $isCart = false) {
		$snippets = Shopware()->Container()->get('snippets')->getNamespace('frontend/plugins/nnwebVoucherLink');
		
		if (!empty($voucherCode)) {
			$basket = Shopware()->Modules()->Basket();
			$voucher = $basket->sAddVoucher($voucherCode);
				
			if ($voucher === false) {
	        	
				$message = $snippets->get('voucher', 'Der Gutscheincode');
				$message .= ' <b>' . $voucherCode . '</b> ';
				
				if (!empty($this->config["nnwebVoucherLink_voucherInfo"])) {
					$voucherInfo = $this->getVoucherInfo($voucherCode);
					if (!empty($voucherInfo))
						$message .= '(' . $this->getVoucherInfo($voucherCode) . ') ';
				}
				
				$message .= $snippets->get('saved', 'wurde gespeichert und wird automatisch in den Warenkorb gelegt!');
				$type = 'success';
				Shopware()->Session()->nnwebVoucherLinkCode = $voucherCode;
			
			} elseif ($voucher['sErrorFlag'] == 1) {
				
				$message = implode(', ', $voucher['sErrorMessages']);
				Shopware()->Session()->nnwebVoucherLinkCode = $voucherCode;
				$type = 'warning';
				
			} else {
				
				$message = $snippets->get('voucher', 'Der Gutscheincode');
				$message .= ' <b>' . $voucherCode . '</b> ';
				
				if (!empty($this->config["nnwebVoucherLink_voucherInfo"])) {
					$voucherInfo = $this->getVoucherInfo($voucherCode);
					if (!empty($voucherInfo))
						$message .= '(' . $this->getVoucherInfo($voucherCode) . ') ';
				}
				
				if ($isCart) {
					$message .= $snippets->get('redeemed', 'wurde erfolgreich eingelöst!');
				} else {
					$message .= $snippets->get('saved', 'wurde gespeichert und wird automatisch in den Warenkorb gelegt!');
				}
				Shopware()->Session()->nnwebVoucherLinkCode = "";
				$type = 'success';
			}
		}
		$view->assign('nnwebVoucherLinkMessage', $message);
		$view->assign('nnwebVoucherLinkMessageType', $type);
	}

	private function getVoucherInfo($voucherCode) {
		$voucherDetails = Shopware()->Db()->fetchRow('SELECT sev.*, sas.name as supplierName
              FROM s_emarketing_vouchers sev
			  LEFT JOIN s_emarketing_voucher_codes sevc ON sev.id = sevc.voucherID
			  LEFT JOIN s_articles_supplier sas ON sev.bindtosupplier = sas.id
              WHERE (sev.vouchercode = ? OR sevc.code = ?)
              AND (
                (sev.valid_to >= CURDATE() AND sev.valid_from <= CURDATE())
                OR sev.valid_to IS NULL
              )', [strtolower($voucherCode), strtolower($voucherCode)]) ?: [];
		
		$voucherInfo = "";
		if (!empty($voucherDetails)) {
			if (empty($voucherDetails["percental"])) {
				$voucherInfo .= $voucherDetails["value"] . "€";
			} else {
				$voucherInfo .= $voucherDetails["value"] . "%";
			}
			
			if (!empty($voucherDetails["minimumcharge"])) {
				$voucherInfo .= " ab " . $voucherDetails["minimumcharge"] . "€ Bestellwert";
			}
			
			if (!empty($voucherDetails["supplierName"])) {
				$voucherInfo .= " auf " . $voucherDetails["supplierName"] . " Produkte";
			}
		}
		return $voucherInfo;
	}
}