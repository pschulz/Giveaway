<?php
/**
 * Sitewards_Giveaway_Helper_Data
 *
 * A helper class to read all the extension setting variables
 *
 * @category    Sitewards
 * @package     Sitewards_Giveaway
 * @copyright   Copyright (c) 2012 Sitewards GmbH (http://www.sitewards.com/de/)
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class Sitewards_Giveaway_Helper_Data extends Mage_Core_Helper_Abstract {
	/**
	 * Add cart action
	 *
	 * @type integer
	 */
	const I_CART_ACTION_ADD    = 0;

	/**
	 * Update cart action
	 *
	 * @type integer
	 */
	const I_CART_ACTION_UPDATE = 1;

	/**
	 * Returns an attribute code to identify a give away product by
	 *
	 * @return string
	 */
	public function getGiveawayIdentifierName() {
		if ( $this->isExtensionEnabled() == true ) {
			return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/giveaway_identifier_name');
		}
	}

	/**
	 * Returns the number of giveaway items allowed per cart instance
	 *
	 * @return integer
	 */
	public function getGiveawaysPerCart() {
		if ( $this->isExtensionEnabled() == true ) {
			return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/giveaways_per_cart');
		}
	}

	/**
	 * Returns min total to add giveaways
	 *
	 * @return integer
	 */
	protected function getMinTotal() {
		if ( $this->isExtensionEnabled() == true ) {
			return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/min_total');
		}
	}

	/**
	 * Returns the number of product in cart needed to add giveaways
	 *
	 * @return integer
	 */
	protected function getNonGiveawaysPerCart() {
		if ( $this->isExtensionEnabled() == true ) {
			return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/min_products');
		}
	}

	/**
	 * Returns true is extension is active
	 *
	 * @return boolean
	 */
	public function isExtensionEnabled() {
		return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/enable_ext');
	}

	/**
	 * @return boolean
	 */
	public function isForwardToGiveawaysPageEnabled(){
		return Mage::getStoreConfig('sitewards_giveaway_config/sitewards_giveaway_general/giveaways_forward_to_list');
	}

	/**
	 * Returns the page for giveaway products
	 *
	 * @return string
	 */
	public function getGiveawaysPage(){
		return 'giveaway';
	}

	/**
	 * Return giveaway identifier value by product id
	 *
	 * @param integer $iProductId
	 *
	 * @return boolean
	 */
	public function getGiveawayIdentifierValue($iProductId){
		$bIsGiveaway = (boolean) Mage::getModel('catalog/product')
			->getResource()
			->getAttributeRawValue(
				$iProductId,
				$this->getGiveawayIdentifierName(),
				Mage::app()->getStore()
			);
		return $bIsGiveaway;
	}

	/**
	 * Return the number of giveaway products in the cart
	 *
	 * @return integer
	 */
	public function getCartGiveawayProductsAmounts(){
		return array_sum($this->getCartProductsAmounts(true));
	}

	/**
	 * Return the number of non giveaway products in the cart
	 *
	 * @return integer
	 */
	public function getCartNonGiveawayProductsAmounts(){
		return array_sum($this->getCartProductsAmounts(false));
	}

	/**
	 * Returns if user can add giveaway products to his cart
	 *
	 * @return boolean|integer
	 */
	public function canAddGiveawaysToCart(){
		return ($this->canHaveMoreGiveaways()
			&& $this->hasEnoughNonGiveaways()
			&& $this->hasEnoughTotal()
			&& $this->hasSiteGiveaways()
		);
	}

	/**
	 * returns if sessions grand total is at least the defined min_total
	 *
	 * @return bool
	 */
	private function hasEnoughTotal() {
		$oCheckoutSession = Mage::getSingleton('checkout/session');
		if ($oCheckoutSession->getQuote()->getBaseGrandTotal() < $this->getMinTotal()) {
			return false;
		}
		return true;
	}

	/**
	 * returns if user has enough non giveaways in cart
	 */
	private function hasEnoughNonGiveaways() {
		if ($this->getCartNonGiveawayProductsAmounts() < $this->getNonGiveawaysPerCart()) {
			return false;
		}
		return true;
	}

	/**
	 * returns if user has not already all possible giveaways in cart
	 *
	 * @return bool
	 */
	private function canHaveMoreGiveaways() {
		if ($this->getCartGiveawayProductsAmounts() >= $this->getGiveawaysPerCart()) {
			return false;
		}
		return true;
	}

	/**
	 * Returns the default order qty for a product by provided product id
	 *
	 * @param integer|boolean $iProductId
	 * @return integer|float
	 * @throws Exception
	 * 	if the product id is not an integer greater than zero
	 */
	public function getDefaultOrderQtyForProductId($iProductId = false){

		if (intval($iProductId) != $iProductId || intval($iProductId) <= 0){
			throw new Exception("ProductId must be an integer greater than 0.");
		}

		$oProductViewBlock = Mage::app()->getLayout()->createBlock('catalog/product_view');
		$oProduct = Mage::getModel('catalog/product')->load($iProductId);
		$mDefaultQty = $oProductViewBlock->getProductDefaultQty($oProduct);

		return $mDefaultQty;
	}

	/**
	 * Checks if customer can proceed to checkout.
	 * If his cart contains only giveaway products,
	 * or giveaway products are more than allowed,
	 * there is no checkout possible
	 *
	 * @return bool
	 */
	public function isCartValidForCheckout(){
		/* @var $oCartHelper Mage_Checkout_Helper_Cart */
		$oCartHelper		= Mage::helper('checkout/cart');
		$oCart				= $oCartHelper->getCart();
		$oCartItems			= $oCart->getItems();
		$bValidCart			= false;

		// loop through all products in the cart and set $bValidCart
		// to true if we have at least one non-giveaway product
		foreach ($oCartItems as $oItem) {
			$iProductId = $oItem->getProductId();
			if ( $this->getGiveawayIdentifierValue($iProductId) != true ) {
				$bValidCart = true;
			}
		}

		$bHasDeprecatedGiveawayAmount = $this->getCartGiveawayProductsAmounts() > $this->getGiveawaysPerCart();
		$bHasEnoughNonGiveaways       = $this->getCartGiveawayProductsAmounts() > 0 && !$this->hasEnoughNonGiveaways();
		if ($bHasDeprecatedGiveawayAmount || $bHasEnoughNonGiveaways || !$this->hasEnoughTotal()) {
			$bValidCart = false;
		}

		// if the cart is empty, it's valid too
		if (count($oCartItems) == 0) {
			$bValidCart = true;
		}

		return $bValidCart;
	}

	/**
	 * Returns an array of non giveaway product ids and their amount in the cart
	 *
	 * @param bool $bIsGiveaway
	 * @return array
	 */
	private function getCartProductsAmounts($bIsGiveaway = false) {
		$oCart = Mage::getSingleton('checkout/cart');
		$aProductsInCart = array();
		foreach ($oCart->getItems() as $oItem) {
			$iProductId = $oItem->getProductId();
			if ($this->getGiveawayIdentifierValue($iProductId) == $bIsGiveaway) {
				$aProductsInCart[$iProductId] = $oItem->getQty();
			}
		}
		return $aProductsInCart;
	}

	/**
	 * Check to see if there are giveaway products available on the site
	 *
	 * @return bool
	 */
	public function hasSiteGiveaways() {
		/* @var $oProduct Mage_Catalog_Model_Product */
		$oProduct = Mage::getModel('catalog/product');
		/* @var $oCollection Mage_Catalog_Model_Resource_Product_Collection */
		$oCollection = $oProduct->getResourceCollection()
			->addStoreFilter()
			->addAttributeToFilter($this->getGiveawayIdentifierName(), true);

		Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($oCollection);
		Mage::getSingleton('catalog/product_status')->addSaleableFilterToCollection($oCollection);

		if($oCollection->getSize() > 0) {
			return true;
		}
	}

	/**
	 * Get all items in the cart
	 * Get all items adding to the cart
	 * Calculate cart params
	 * Compare cart params with config limits and add notices
	 *
	 * @param array $aProductInformation - an array with each element containing product id and quantity
	 * @param string $sCartAction - cart action
	 * @return boolean
	 */
	public function canAddProducts($aProductInformation, $sCartAction = self::I_CART_ACTION_UPDATE) {
		if ($sCartAction == self::I_CART_ACTION_ADD
			&& $this->getGiveawayIdentifierValue($aProductInformation[0]['id']) == false
		) {
			return true;
		}
		/** @var $oCheckoutSession Mage_Checkout_Model_Session */
		$oCheckoutSession       = Mage::getSingleton('checkout/session');
		$sGiveawayAttributeCode = $this->getGiveawayIdentifierName();

		/*
		 * Config limits
		 */
		$iGiveawayMaxCount    = (int)$this->getGiveawaysPerCart();
		$iNonGiveawayMinCount = (int)$this->getNonGiveawaysPerCart();
		$iMinTotal            = (float)$this->getMinTotal();

		/*
		 * Calculate cart params according to the cart action
		 */
		$iTotalGiveawayQty    = 0;
		$iTotalNonGiveawayQty = 0;
		$iBaseGrandTotal      = 0;
		if ($sCartAction == self::I_CART_ACTION_ADD) {
			$iTotalGiveawayQty    = $this->getCartGiveawayProductsAmounts();
			$iTotalNonGiveawayQty = $this->getCartNonGiveawayProductsAmounts();
			$iBaseGrandTotal      = (float)$oCheckoutSession->getQuote()->getBaseGrandTotal();
		}
		$bUpdateIsGiveaway = false;
		foreach($aProductInformation as $aProduct) {
			$oProduct = Mage::getModel('catalog/product');
			$oProduct->load($aProduct['id']);
			if ($oProduct->getData($sGiveawayAttributeCode) == true) {
				$bUpdateIsGiveaway  = true;
				$iTotalGiveawayQty += $aProduct['qty'];
			} else {
				$iTotalNonGiveawayQty += $aProduct['qty'];
			}
			$iBaseGrandTotal += $aProduct['qty'] * $oProduct->getPrice();
		}

		/*
		 * Compare cart params with config limits
		 */
		if ($bUpdateIsGiveaway == true) {
			$sMessage = $this->__('Cannot add item(s) to your shopping cart.');
			if($iTotalGiveawayQty > $iGiveawayMaxCount) {
				$sMessage .= ' ' . $this->__('You can have only %s giveaway item(s) per cart.', $iGiveawayMaxCount);
				$oCheckoutSession->addNotice($sMessage);
				return false;
			} elseif ($iTotalNonGiveawayQty < $iNonGiveawayMinCount) {
				$sMessage .= ' ' . $this->__(
					'The total number of items in your cart must be at least %s.',
					$iNonGiveawayMinCount
				);
				$oCheckoutSession->addNotice($sMessage);
				return false;
			} elseif ($iBaseGrandTotal < $iMinTotal) {
				$fFormattedMinTotal = Mage::helper('core')->currency($iMinTotal);
				$sMessage .= ' ' . $this->__('The total of the cart must be at least %s.', $fFormattedMinTotal);
				$oCheckoutSession->addNotice($sMessage);
				return false;
			}
		}
		return true;
	}

	/**
	 * Return update cart product data
	 *
	 * @param Mage_Core_Controller_Request_Http $oRequest
	 * @return array
	 */
	public function getUpdateCartProductInfo(Mage_Core_Controller_Request_Http $oRequest) {
		$aParams = $oRequest->getParams();
		$aProductInformation = array();
		foreach (Mage::getSingleton('checkout/cart')->getItems() as $iItemIndex => $oItem) {
			if (isset($aParams['cart'][$iItemIndex])) {
				$aProductInformation[] = array(
					'id'  => $oItem->getProductId(),
					'qty' => $aParams['cart'][$iItemIndex]['qty']
				);
			}
		}
		return $aProductInformation;
	}

	/**
	 * Return add cart product data
	 *
	 * @param Mage_Core_Controller_Request_Http $oRequest
	 * @return array
	 */
	public function getAddCartProductInfo(Mage_Core_Controller_Request_Http $oRequest) {
		$aParams = $oRequest->getParams();
		if (isset($aParams['qty'])) {
			$iQty = (int)$aParams['qty'];
		} else {
			$iQty = $this->getDefaultOrderQtyForProductId($aParams['product']);
		}
		$aProductInformation[] = array(
			'id'  => $aParams['product'],
			'qty' => $iQty
		);
		return $aProductInformation;
	}
}