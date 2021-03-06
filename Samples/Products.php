<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';
require_once __DIR__ . '/AbstractSample.php';

class Products extends AbstractSample
{
	/**
	 * @return \Rbs\Store\Documents\WebStore
	 */
	protected function getWebStore()
	{
		$query = $this->getDocumentManager()->getNewQuery('Rbs_Store_WebStore');
		$webStore = $query->getFirstDocument();
		return $webStore;
	}

	public function install()
	{
		$this->registerServices();

		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$webStore = $this->getWebStore();
		$billingArea = $webStore->getBillingAreas()[0];

		$this->log('Install images...');
		$allImages = array();
		$imagesData = json_decode(file_get_contents(__DIR__ . '/Assets/catalog-images.json'), true);
		foreach ($imagesData as $imageData)
		{
			$image = $this->addImage($imageData);
			$this->log(' - ' . $image->getLabel());
			$allImages[$image->getLabel()] = $image->getId();
		}

		$this->log('Install brands ...');
		$allBrands = array();
		$brandsData = json_decode(file_get_contents(__DIR__ . '/Assets/catalog-brands.json'), true);
		foreach ($brandsData as $brandData)
		{
			$brand = $this->addBrand($brandData, $allImages);
			$this->log(' - ' . $brand->getLabel());
			$allBrands[$brand->getLabel()] = $brand->getId();
		}

		$this->log('Install products ...');
		$attrGroup = $this->getAttributeGroup();
		$allProduct = array();
		$allSKU = array();
		$productsData = json_decode(file_get_contents(__DIR__ . '/Assets/catalog-products.json'), true);
		foreach ($productsData as $productData)
		{
			$productCode = $productData['code'];
			$product = $this->addProduct($productData, $allImages, $allBrands, $attrGroup);
			$this->log(' - ' . $product->getLabel());

			$allProduct[$productCode] = $product->getId();
			$allSKU[$productCode] = $product->getSku()->getId();
		}

		$this->log('Install prices ...');
		$pricesData = json_decode(file_get_contents(__DIR__ . '/Assets/catalog-prices.json'), true);
		foreach ($pricesData as $priceData)
		{
			$priceData["sku"] = $allSKU[$priceData["product"]];
			$priceData["webStore"] = $webStore->getId();
			$priceData["billingArea"] = $billingArea->getId();
			$price = $this->addPrice($priceData);
			$this->log(' - ' . $price->getSku()->getCode() . ' / ' . $price->getLabel());
		}

		$this->log('Install product lists...');

		$facets = $this->getDocumentManager()->getNewQuery('Rbs_Elasticsearch_Facet')
			->addOrder('id')->getDocuments()->toArray();

		$commerceServices = $this->getCommerceServices();
		$cm = $commerceServices->getCatalogManager();
		$commerceWebStoreInitializationContext = 'Rbs Commerce WebStore Initialize ' . $this->getDefaultWebsite()->getId() . ' ' . $webStore->getId();
		$shopTopics = $this->getApplicationServices()->getDocumentCodeManager()->getDocumentsByCode('rbs_commerce_initialize_store_topic', $commerceWebStoreInitializationContext);
		$template = $this->getPageTemplate('Rbs_Blank_NoSidebarPage');
		$templateSideBar = $this->getPageTemplate('Rbs_Blank_SidebarPage');
		if (isset($shopTopics[0]) && $shopTopics[0] != null)
		{
			$shopTopic = $shopTopics[0];
		}
		else
		{
			$this->log('ERROR: user account topic can\'t be found after the web store initialization');
			return;
		}

		$data = array(
			'Ordinateurs' => array('MICRO-COMMODORE64', 'MICRO-ATARI-ST', 'MICRO-AMIGA'),
			'Accessoires' => array('NES-ZAPPER', 'NINTENDO-CONTROLLER', 'NES-ADVANTAGE', 'SUPER-NINTENDO-CONTROLLER',
				"GAMEBOY-LINKCABLE"),
			'Nintendo' => array('DUCK-HUNT', 'NES-ZAPPER', 'NINTENDO-CONTROLLER', 'NES-ADVANTAGE', "NES-1985",
				"FAMICOM-1983", "SUPER-NINTENDO", "SUPER-NINTENDO-CONTROLLER", "SNES-GAME-ZELDA", "SNES-DOWNLOAD-ZELDA-TIPS",
				"GAMEBOY", "GAMEBOY-GAME-POKEMON", "GAMEBOY-LINKCABLE"),
			'Tous' => array_keys($allProduct)
		);

		foreach ($data as $title => $productCodes)
		{
			$this->log('Add SectionProductList: ' . $title);

			$topic = $this->getTopic($shopTopic, $title);

			/* @var $productList \Rbs\Catalog\Documents\SectionProductList */
			$productList = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_SectionProductList');
			$productList->setLabel($title);
			if (count($facets))
			{
				$productList->setFacets($facets);
			}
			$productList->setProductSortOrder('title');
			$productList->setProductSortDirection('asc');
			$productList->setSynchronizedSection($topic);
			$productList->save();

			$this->addProductListPage($topic, $templateSideBar);

			/* @var $product \Rbs\Catalog\Documents\Product */
			foreach ($productCodes as $productCode)
			{
				$product = $this->getDocumentManager()->getDocumentInstance($allProduct[$productCode]);
				$cm->addProductInProductList($product, $productList, null);
			}
		}

		$data = array(
			'Top ventes' => array('MICRO-COMMODORE64', "GAMEBOY-GAME-POKEMON", 'MICRO-AMIGA', 'SUPER-NINTENDO-CONTROLLER'),
			'Coups de coeur' => array('MICRO-COMMODORE64', 'DUCK-HUNT', 'MICRO-AMIGA', "SNES-DOWNLOAD-ZELDA-TIPS", "GAMEBOY")
		);

		foreach ($data as $title => $productCodes)
		{
			$this->log('Add ProductList: ' . $title);

			/* @var $productList \Rbs\Catalog\Documents\ProductList */
			$productList = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_ProductList');
			$productList->setLabel($title);
			$productList->setProductSortOrder('title');
			$productList->setProductSortDirection('asc');
			$productList->save();

			/* @var $product \Rbs\Catalog\Documents\Product */
			foreach ($productCodes as $productCode)
			{
				$product = $this->getDocumentManager()->getDocumentInstance($allProduct[$productCode]);
				$cm->addProductInProductList($product, $productList, null);
			}
		}

		$transactionManager->commit();
	}

	/**
	 * @return \Rbs\Catalog\Documents\Attribute
	 */
	protected function getAttributeGroup()
	{
		$query = $this->getApplicationServices()->getDocumentManager()->getNewQuery('Rbs_Catalog_Attribute');
		$query->andPredicates($query->eq('valueType', 'Group'));
		$query->addOrder('id');
		return $query->getFirstDocument();
	}

	/**
	 * @param array $data
	 * @return \Rbs\Media\Documents\Image
	 */
	public function addImage($data)
	{
		$this->getApplicationServices()->getStorageManager();

		/* @var $image \Rbs\Media\Documents\Image */
		$image = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Media_Image');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'path')
			{
				$originalPath = __DIR__ . '/Assets/media/' . $restValue;
				$storageURI = 'change://images/' . $restValue;
				file_put_contents($storageURI, file_get_contents($originalPath));
				$restValue = $storageURI;
			}
			$property = $image->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($image, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$image->save();
		return $image;
	}

	/**
	 * @param array $data
	 * @param $allImage
	 * @return \Rbs\Brand\Documents\Brand
	 */
	public function addBrand($data, $allImage)
	{
		/* @var $brand \Rbs\Brand\Documents\Brand */
		$brand = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Brand_Brand');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName == 'visual')
			{
				$restValue = $allImage[$restValue];
			}
			$property = $brand->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($brand, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$brand->save();
		return $brand;
	}

	/**
	 * @param array $data
	 * @param array $allImages
	 * @param array $allBrands
	 * @param \Rbs\Catalog\Documents\Attribute $attrGroup
	 * @return \Rbs\Catalog\Documents\Product
	 */
	public function addProduct($data, $allImages, $allBrands, $attrGroup)
	{
		/* @var $product \Rbs\Catalog\Documents\Product */
		$product = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_Product');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName === 'sku')
			{
				if (is_array($restValue))
				{
					$product->setSku($this->addSku($restValue, $data['code']));
					$product->setNewSkuOnCreation(false);
				}
				else
				{
					$product->setNewSkuOnCreation(true);
				}
				continue;
			}
			if ($propertyName === 'brand')
			{
				$restValue = $allBrands[$restValue];
			}
			elseif ($propertyName === 'visuals')
			{
				foreach ($restValue as $i => $v)
				{
					$restValue[$i] = $allImages[$v];
				}
			}
			$property = $product->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($product, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		$product->setAttribute($attrGroup);
		$product->save();
		return $product;
	}

	/**
	 * @param $data
	 * @param $defaultCode
	 * @return \Rbs\Stock\Documents\Sku
	 */
	public function addSku($data, $defaultCode)
	{
		/* @var $sku \Rbs\Stock\Documents\Sku */
		$sku = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Stock_Sku');
		$sku->setCode($defaultCode);
		foreach ($data as $propertyName => $restValue)
		{
			$property = $sku->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($sku, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}

		$sku->save();
		if (isset($data['level']))
		{
			$commerceServices = $this->getCommerceServices();
			$commerceServices->getStockManager()->setInventory($data['level'], $sku);
		}
		return $sku;
	}

	/**
	 * @param array $data
	 * @return \Rbs\Price\Documents\Price
	 */
	public function addPrice($data)
	{
		/* @var $price \Rbs\Price\Documents\Price */
		$price = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Price_Price');
		$price->setStartActivation(new \DateTime());
		$basePriceValue = null;
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName === 'baseValue')
			{
				$basePriceValue = $restValue;
				continue;
			}
			$property = $price->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\V1\PropertyConverter($price, $property, $this->getDocumentManager());
				$c->setPropertyValue($restValue);
			}
		}
		if ($basePriceValue)
		{
			/* @var $basePrice \Rbs\Price\Documents\Price */
			$basePrice = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Price_Price');
			$basePrice->setStartActivation(new \DateTime('2013-01-01 00:00:00'));
			$basePrice->setSku($price->getSku());
			$basePrice->setWebStore($price->getWebStore());
			$basePrice->setBillingArea($price->getBillingArea());
			$basePrice->setTaxCategories($price->getTaxCategories());
			$basePrice->setValue($basePriceValue);
			$basePrice->save();

			$price->setBasePrice($basePrice);
		}
		$price->save();
		return $price;
	}

	/**
	 * @param \Rbs\Website\Documents\Section $section
	 * @param \Rbs\Theme\Documents\Template $template
	 * @return \Rbs\Website\Documents\FunctionalPage
	 */
	public function addProductListPage($section, $template) {

		$content = json_decode('{"sidebarContent":{"type":"container","id":"sidebarContent","parameters":[],"items":[{"type":"block","id":3,"parameters":{"TTL":0,"useCurrentSectionProductList":true,"searchMode":false,"showUnavailable":true},"name":"Rbs_Elasticsearch_StoreFacets","label":"Rbs_Elasticsearch_StoreFacets","visibility":null}],"grid":12},"mainContent":{"type":"container","id":"mainContent","parameters":[],"items":[{"type":"block","id":4,"parameters":{"TTL":60,"useCurrentSectionProductList":true,"contextualUrls":true,"itemsPerLine":3,"itemsPerPage":9,"showOrdering":true,"showUnavailable":true},"name":"Rbs_Catalog_ProductList","label":"Rbs_Catalog_ProductList","visibility":null}],"grid":12}}');
		$page = $this->getFunctionalPage($section->getWebsite(), $template, 'Liste de produits', $content);
		$this->setSectionPageFunction($section, $page, 'Rbs_Website_Section');
		return $page;
	}
}

$sample = new Products();
$sample->install();
