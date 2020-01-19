<?php

namespace App\Command;

use App\Helper\ApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This commands reads a json source file and create a category tree on shopware instance configured
 *
 * Class CategoryImportCommand
 *
 * @package App\Command
 * @author  Anteneh Gebeyaw <antenehgeb@gmail.com>
 */
class CategoryImportCommand extends Command {

	const LANG_DE = 'de';
	const LANG_EN = 'en';

	// the name of the command (the part after "./console")
	protected static $defaultName = 'etribe:import-categories';

	protected function configure() {
		$this->addOption(
			'source',
			null,
			InputOption::VALUE_OPTIONAL,
			'The source json file for categories',
			false
		);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) {

		/**
		 * read categories from arguments, Or otherwise use the sample json provided in the email
		 */
		if ( $input->hasOption( 'source' ) && $filename = $input->getOption( 'source' ) ) {
			$categories = @file_get_contents( $filename );
		} else {
			$categories = @file_get_contents( "./data/categories-sample.json" );
		}

		if ( $categories === false ) {
			print "ERROR: Cannot read source file \n";

			return 1;
		}

		$categories_a = json_decode( $categories, true );
		try {
			$grouped_categories = $this->groupCategories( $categories_a );
			foreach ( $grouped_categories as $lang => $shop_categories ) {  //language shops
				$shop_main_cat_id = $lang == self::LANG_DE ? $_ENV['DE_SHOP_CAT_ID'] : $_ENV['EN_SHOP_CAT_ID'];
				print "\nWorking on language shop {$lang}...";

				foreach ( $shop_categories as $product_line_area_name => $titles ) { //line areas
					$product_line_area_cat_id = $this->createCategory( $product_line_area_name, $shop_main_cat_id );

					if ( ! empty( $product_line_area_cat_id ) ) {
						print "\n\tCreated product line category {$product_line_area_name} with id {$product_line_area_cat_id}";
						foreach ( array_unique( $titles ) as $title ) { //courses
							$title_cat_id = $this->createCategory( $title, $product_line_area_cat_id );

							if ( ! empty( $title_cat_id ) ) {
								print "\n\t\tSuccessfully create title category {$title} with id {$title_cat_id}";
							} else {
								print "\n\t\tFailed to create title category {$title}";
							}
						}
					} else {
						print "\n\tFailed to create title category {$product_line_area_name}";
					}
				}
			}
		} catch ( \Exception $e ) {
			print "\An error has occurred {$e->getMessage()}";
		}

		return 0;
	}


	/**
	 * Group categories into three-hierarchical tree.
	 *
	 * Considering that each country(language) shop assigns a main category for the language,
	 * it is taken as the root category
	 *
	 * Under each language category, there will be product_line_area and under it will be the category given as `title`
	 *
	 * @param array $categories
	 *
	 * @return array
	 */
	private function groupCategories( $categories ) {
		$grouped_categories = [];
		foreach ( $categories as $category ) {
			$lang                                                            = $category['lang'] ?? self::LANG_EN;
			$grouped_categories[ $lang ][ $category['product_line_area'] ][] = $category['title'];
		}

		return $grouped_categories;
	}


	/**
	 * Makes the create category API call and returns result
	 *
	 * @param string $name
	 * @param int    $parent_id
	 *
	 * @return bool|int
	 * @throws \Exception
	 */
	private function createCategory( $name, $parent_id ) {
		$result = ApiClient::init()->post( 'categories', [
			'name'     => $name,
			'parentId' => $parent_id,
		] );

		return $result['data']['id'] ?? false;
	}
}
