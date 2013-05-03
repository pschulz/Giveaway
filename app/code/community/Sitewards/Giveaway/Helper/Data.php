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
	 * Returns if the product is a giveaway
	 *
	 * @param Mage_Catalog_Model_Product $oProduct
	 *
	 * @return boolean
	 */
	public function isProductGiveaway(Mage_Catalog_Model_Product $oProduct){
		return (boolean)$oProduct->getData($this->getGiveawayIdentifierName());
	}

	/**
	 * Returns an array of giveaway product ids and their amount in the cart
	 *
	 * @return array
	 */
	public function getCartGiveawayProductsAmounts(){
		return $this->getCartProductsAmounts(true);
	}

	/**
	 * Returns an array of non giveaway product ids and their amount in the cart
	 *
	 * @return array
	 */
	public function getCartNonGiveawayProductsAmounts(){
		return $this->getCartProductsAmounts(false);
	}

	/**
	 * Returns if user can add giveaway products to his cart
	 *
	 * @return boolean|integer
	 */
	public function canAddGiveawaysToCart(){
		return ($this->canHaveMoreGiveaways() AND $this->hasEnoughNonGiveaways());
	}

	/**
	 * returns if user has enough non giveaways in cart
	 */
	private function hasEnoughNonGiveaways() {
		$aNonGiveawayProductsInCart = $this->getCartNonGiveawayProductsAmounts();
		if (array_sum($aNonGiveawayProductsInCart) < $this->getNonGiveawaysPerCart()) {
			return true;
		} else {
			Mage::getSingleton('checkout/session')->addNotice($this->__('Cannot add the item to shopping cart. You need more products in cart.'));
			return false;
		}

	}

	/**
	 * returns if user has not already all possible giveaways in cart
	 *
	 * @return bool
	 */
	private function canHaveMoreGiveaways() {
		$aGiveawayProductsInCart = $this->getCartGiveawayProductsAmounts();
		if (array_sum($aGiveawayProductsInCart) < $this->getGiveawaysPerCart()) {
			return true;
		} else {
			Mage::getSingleton('checkout/session')->addNotice($this->__('Cannot add the item to shopping cart. You have already reached your limit of giveaway products.'));
			return false;
		}
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
	 * If his cart contains only giveaway products, there is no checkout possible
	 *
	 * @return bool
	 */
	public function isCartValidForCheckout(){
		/* @var $oCartHelper Mage_Checkout_Helper_Cart */
		$oCartHelper		= Mage::helper('checkout/cart');
		$oCart				= $oCartHelper->getCart();
		$oCartItems			= $oCart->getItems();
		$bValidCart			= false;
		$oProduct = Mage::getModel('catalog/product');

		// loop through all products in the cart and set $bValidCart
		// to true if we have at least one non-giveaway product
		foreach ($oCartItems as $oItem) {
			$iProductId = $oItem->getProductId();
			$oProduct->load($iProductId);
			if ( $oProduct->getData($this->getGiveawayIdentifierName()) != true ) {
				$bValidCart = true;
			}
			$oProduct->clearInstance();
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
	 * @return array
	 */
	private function getCartProductsAmounts($bIsGiveaway = false) {
		$oCart = Mage::getSingleton('checkout/cart');
		$oProduct = Mage::getModel('catalog/product');
		$oItems = $oCart->getItems();

		$aProductsInCart = array();
		foreach ($oItems as $oItem) {
			$oCollection = $oProduct
				->getResourceCollection()
				->addAttributeToFilter('entity_id', $oItem->getData('product_id'))
				->addAttributeToFilter($this->getGiveawayIdentifierName(), $bIsGiveaway);
			$aProductId = $oCollection->getAllIds();
			// if the $aProductId is not empty, then we have a giveaway product
			if (!empty($aProductId)) {
				$aProductsInCart[$oItem->getData('product_id')] = $oItem->getData('qty');
			}
		}

		return $aProductsInCart;
	}
}