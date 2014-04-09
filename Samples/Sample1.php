<?php
define('PROJECT_HOME', getcwd());
require_once PROJECT_HOME . '/Change/Application.php';

use Change\Documents\Query\Query;

/**
* @name \Rbs\Catalog\Setup\Samples\Sample1
*/
class Sample1
{

	/**
	 * @var \Change\Application
	 */
	protected $application;

	/**
	 * @var \Change\Application\ApplicationServices
	 */
	protected $applicationServices;

	/**
	 * @var \Change\Documents\DocumentServices
	 */
	protected $documentServices;

	/**
	 * @var \Change\Presentation\PresentationServices
	 */
	protected $presentationServices;

	/**
	 * @return \Change\Application
	 */
	protected function getApplication()
	{
		if (!$this->application)
		{
			$this->application = new \Change\Application();
		}
		return $this->application;
	}

	/**
	 * @return \Change\Application\ApplicationServices
	 */
	public function getApplicationServices()
	{
		if (!$this->applicationServices)
		{
			$this->applicationServices  = new \Change\Application\ApplicationServices($this->getApplication());;
		}
		return $this->applicationServices;
	}

	/**
	 * @return \Change\Documents\DocumentServices
	 */
	public function getDocumentServices()
	{
		if (!$this->documentServices)
		{
			$this->documentServices = new \Change\Documents\DocumentServices($this->getApplicationServices());
		}
		return $this->documentServices;
	}

	/**
	 * @return \Change\Documents\DocumentManager
	 */
	public function getDocumentManager()
	{

		return $this->getDocumentServices()->getDocumentManager();
	}


	public function __construct()
	{
		$this->getApplication()->start();
	}

	protected function log($data)
	{
		if (is_string($data))
		{
			echo $data;
		}
		else
		{
			var_export($data);
		}
		echo PHP_EOL;
	}


	/**
	 * @return \Rbs\Price\Documents\Tax
	 */
	protected function getTaxByeCode($code)
	{
		$query = new Query($this->getDocumentServices(), 'Rbs_Price_Tax');
		$query->andPredicates($query->eq('code', $code));
		return $query->getFirstDocument();
	}

	/**
	 * @return \Rbs\Price\Documents\BillingArea
	 */
	protected function getBillingArea()
	{
		$query = new Query($this->getDocumentServices(), 'Rbs_Price_BillingArea');
		$billingArea = $query->getFirstDocument();
		if ($billingArea === null)
		{
			/* @var $billingArea \Rbs\Price\Documents\BillingArea */
			$billingArea = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Price_BillingArea');
			$billingArea->setLabel('Sample FR Billing Area');
			$billingArea->setCode('FR');
			$billingArea->setCurrencyCode('EUR');
			$billingArea->setTaxes(array($this->getTaxByeCode('TVAFR')));
			$billingArea->setBoEditWithTax(true);
			$billingArea->save();
		}

		return $billingArea;
	}

	/**
	 * @return \Rbs\Store\Documents\WebStore
	 */
	protected function getWebStore()
	{
		$query = new Query($this->getDocumentServices(), 'Rbs_Store_WebStore');
		$webStore = $query->getFirstDocument();
		if ($webStore === null)
		{
			/* @var $webStore \Rbs\Store\Documents\WebStore */
			$webStore = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Store_WebStore');
			$webStore->setLabel('Sample Web Store');
			$webStore->setBillingAreas(array($this->getBillingArea()));
			$webStore->save();
		}

		return $webStore;
	}

	public function install()
	{
		$transactionManager = $this->getApplicationServices()->getTransactionManager();
		$transactionManager->begin();

		$webStore = $this->getWebStore();
		$billingArea = $webStore->getBillingAreas()[0];

		$this->log('Install images...');
		$allImages = array();
		$imagesData = json_decode(file_get_contents(__DIR__ .'/Assets/images.json'), true);
		foreach($imagesData as $imageData)
		{
			$image = $this->addImage($imageData);
			$allImages[$image->getLabel()] = $image->getId();
		}

		$this->log('Install brands ...');
		$allBrands = array();
		$brandsData = json_decode(file_get_contents(__DIR__ .'/Assets/brands.json'), true);
		foreach($brandsData as $brandData)
		{
			$brand = $this->addBrand($brandData, $allImages);
			$allBrands[$brand->getLabel()] = $brand->getId();
		}

		$this->log('Install products ...');
		$allProduct = array();
		$allSKU = array();
		$productsData = json_decode(file_get_contents(__DIR__ .'/Assets/products.json'), true);
		foreach($productsData as $productData)
		{
			$product = $this->addProduct($productData, $allImages, $allBrands);

			$allProduct[$product->getCode()] = $product->getId();
			$allSKU[$product->getCode()] = $product->getSku()->getId();
		}

		$this->log('Install prices ...');
		$pricesData = json_decode(file_get_contents(__DIR__ .'/Assets/prices.json'), true);
		foreach($pricesData as $priceData)
		{
			$priceData["sku"] = $allSKU[$priceData["product"]];
			$priceData["webStore"] = $webStore->getId();
			$priceData["billingArea"] = $billingArea->getId();
			$price = $this->addPrice($priceData);
		}

		$rootNode = $this->getDocumentServices()->getTreeManager()->getRootNode('Rbs_Catalog');

		$this->log('Install categories ...');

		/* @var $category \Rbs\Catalog\Documents\Category */
		$category = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_Category');
		$category->setLabel('Ordinateurs');
		$category->setWebStore($webStore);
		$category->setProductSortOrder('title');
		$category->setProductSortDirection('asc');
		$category->save();
		$this->getDocumentServices()->getTreeManager()->insertNode($rootNode, $category);

		/* @var $category \Rbs\Catalog\Documents\Category */
		$commerceServices = new \Rbs\Commerce\Services\CommerceServices($this->getDocumentServices()->getApplicationServices(), $this->getDocumentServices());
		$cm = $commerceServices->getCatalogManager();

		foreach (array('MICRO-COMMODORE64', 'MICRO-ATARI-ST', 'MICRO-AMIGA') as $productCode)
		{
			$product = $this->getDocumentManager()->getDocumentInstance($allProduct[$productCode]);
			$cm->addProductInCategory($product, $category, null);
		}

		/* @var $category \Rbs\Catalog\Documents\Category */
		$category = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_Category');
		$category->setLabel('Accessoires');
		$category->setWebStore($webStore);
		$category->setProductSortOrder('title');
		$category->setProductSortDirection('asc');
		$category->save();
		$this->getDocumentServices()->getTreeManager()->insertNode($rootNode, $category);

		/* @var $category \Rbs\Catalog\Documents\Category */
		$commerceServices = new \Rbs\Commerce\Services\CommerceServices($this->getDocumentServices()->getApplicationServices(), $this->getDocumentServices());
		$cm = $commerceServices->getCatalogManager();

		foreach (array('NES-ZAPPER', 'NINTENDO-CONTROLLER', 'NES-ADVANTAGE', 'SUPER-NINTENDO-CONTROLLER', "GAMEBOY-LINKCABLE") as $productCode)
		{
			$product = $this->getDocumentManager()->getDocumentInstance($allProduct[$productCode]);
			$cm->addProductInCategory($product, $category, null);
		}

		$transactionManager->commit();
	}

	/**
	 * @param array $data
	 * @return \Rbs\Media\Documents\Image
	 */
	public function addImage($data)
	{
		$storageManager = $this->getDocumentServices()->getApplicationServices()->getStorageManager();

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
				$c = new \Change\Http\Rest\PropertyConverter($image, $property);
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
				$c = new \Change\Http\Rest\PropertyConverter($brand, $property);
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
	 * @return \Rbs\Catalog\Documents\Product
	 */
	public function addProduct($data, $allImages, $allBrands)
	{
		/* @var $product \Rbs\Catalog\Documents\Product */
		$product = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Catalog_Product');
		foreach ($data as $propertyName => $restValue)
		{
			if ($propertyName === 'sku')
			{
				$product->setNewSkuOnCreation(true);
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
				$c = new \Change\Http\Rest\PropertyConverter($product, $property);
				$c->setPropertyValue($restValue);
			}
		}
		$product->save();
		return $product;
	}

	/**
	 * @return \Rbs\Price\Documents\Price
	 */
	public function addPrice($data)
	{
		/* @var $price \Rbs\Price\Documents\Price */
		$price = $this->getDocumentManager()->getNewDocumentInstanceByModelName('Rbs_Price_Price');

		foreach ($data as $propertyName => $restValue)
		{
			$property = $price->getDocumentModel()->getProperty($propertyName);
			if ($property)
			{
				$c = new \Change\Http\Rest\PropertyConverter($price, $property);
				$c->setPropertyValue($restValue);
			}
		}
		$price->save();
		return $price;
	}
}

$sample = new Sample1();
$sample->install();
