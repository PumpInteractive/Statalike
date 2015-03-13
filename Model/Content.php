<?php

require_once(APP.'Plugin'.DS.'Statalike'.DS.'Vendor'.DS.'Dipper'.DS.'Dipper.php'); // YAML Parser
use Statamic\Dipper\Dipper;

require_once(APP.'Plugin'.DS.'Statalike'.DS.'Vendor'.DS.'Markdown'.DS.'Markdown.php'); // Markdown Parser
use Michelf\Markdown;

App::uses('AppModel', 'Model');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');

class Content extends AppModel {

	public $name = "StatalikeContent";
	public $recursive = -1;
	public $useTable = 'statalike_content';

	public $_schema = array(

		'slug' => array(
			'type' => 'text'
		),
		'md5' => array(
			'type' => 'text'
		),
		'rendered_content' => array(
			'type' => 'text'
		)

	);

/*  Methods */

	public function isValidSlug($slug){

		// Make sure there are no ./ or ../ in the slug for security
		if (preg_match('/^[\/\w_-]+$/mi', $slug)) return true;

	}


	public static function cleanSlug($slug){

		preg_match('/\/?[0-9]{4}-[0-9]{2}-[0-2]{2}-/', $slug, $matches, PREG_OFFSET_CAPTURE); // Match the date
		array_reverse($matches); // Work  with  only the last match
		$date = (count($matches) > 0) ? preg_replace('/\/?([0-9]{4}-[0-9]{2}-[0-2]{2})-/', '$1', @$matches[0][0]) : ''; // Clean up the date

		$returnArray =  [];
		$returnArray['slug'] = preg_replace('/(\/)?[0-9]{4}-[0-9]{2}-[0-2]{2}-/', '$1', $slug);

		if(strlen($date) > 0) $returnArray['date'] = $date;

		$components = explode('/', $slug);

		$fileComponent = array_pop($components);

		$folderComponent = '';
		foreach($components as $item){

			$folderComponent .= $item.'/';

		}

		$returnArray['folderComponent'] = $folderComponent;
		$returnArray['fileComponent'] = $fileComponent;

		return $returnArray;

	}

	public function parseYAML($string){

		return Dipper::parse($string);

	}

	public function parseMD($string){

		return Markdown::defaultTransform($string);

	}

	public function parsePage($string){

		$elements = preg_split("/\n---/", $string, 2, PREG_SPLIT_NO_EMPTY);

		$data['content'] = (count($elements) > 1) ? $this->parseMD($elements[1]) : ''; // If no content, set as blank
		$data['yaml'] = $this->parseYAML($elements[0]); // Encode data as JSON for saving to DB

		return $data;

	}

	public function createContentData($file, $slug, $date = false){

		$parsed_page = $this->parsePage($file->read());

		$content = $parsed_page['content'];
		$data = $parsed_page['yaml'];

		if($date) $data['date'] = $date;

		// Create it
		$new_data = array(
			'Content' => array(
				'md5' => $file->md5(),
				'slug' => $slug,
				'data' => json_encode($data),
				'rendered_content' => $content
			)
		);

		return  $new_data;

	}

	public function getContent($slug){

		if(!Configure::check('Statalike.content_folder')) throw new CakeException('Statalike\'s configuration is not set. Please see README.');

		if (!$this->isValidSlug($slug)) throw new UnauthorizedException(); // If slug isn't valid, throw an exception

		$cleanSlug = Content::cleanSlug($slug);

		$folder = new Folder(Configure::read('Statalike.content_folder').$cleanSlug['folderComponent']);

		if(count($folder->find('\/page\.md', false)) == 1){ // Check to see if there is a folder by that name, with a page.md file in  it

			$slug = (substr($slug, -1) == '/') ? substr($slug, 0, -1) : $slug; // Remove the trailing slash, if present
			$md_filename = Configure::read('Statalike.content_folder').$slug."/page.md"; // Set up the filename as the index file
			
		}elseif(count($folder->find($cleanSlug['fileComponent'].'\.md', false)) == 1){ // Check to  see if there is content by that name

			$md_filename = Configure::read('Statalike.content_folder').$slug.".md"; // Set up the filename
	

			$slug = $cleanSlug['slug'];
			if(@$cleanSlug['date']) $date = $cleanSlug['date'];

		}elseif(count($results = $folder->find('[0-9]{4}-[0-9]{2}-[0-2]{2}-'.$cleanSlug['fileComponent'].'\.md', false)) > 0){ // Check  to see if there is dated content with that name in the folder

			$md_filename = Configure::read('Statalike.content_folder').'/'.$cleanSlug['folderComponent'].'/'.$results[0]; // Set up the filename

		}else{

			throw new NotFoundException();

		}

		$file = new File($md_filename);
		$content_data =  [];

		// Check to see if this file exists
		if ($file->exists()){

			// Is this content in the DB?
			$db_data = $this->find('first', array(
				'conditions' => array('slug' => $slug),
			));			

			if(count($db_data) == 1){ // Found it...

				if($db_data['Content']['md5'] == $file->md5()){ // No changes, serve the cached version

					$content_data['content'] = $db_data['Content']['rendered_content'];
					$content_data['yaml'] = json_decode($db_data['Content']['data'], true);

					return $content_data;

				}else{ // Create a new version

					// Not found, need to create it
					$passDate = (@$date) ? $date : false; // Set up the date if present
					$new_data = $this->createContentData($file, $slug, $passDate);
					$new_data['Content']['id'] = $db_data['Content']['id'];

					$this->save($new_data);

					// Pass the data to the view
					$content_data['content'] = $new_data['Content']['rendered_content'];
					$content_data['yaml'] = json_decode($new_data['Content']['data'], true);

					return $content_data;

				}


			}else{

				// Not found, need to create it
				$passDate = (@$date) ? $date : false; // Set up the date if present
				$new_data = $this->createContentData($file, $slug, $passDate);

				$this->save($new_data);

				// Pass the data to the view
				$content_data['content'] = $new_data['Content']['rendered_content'];
				$content_data['yaml'] = json_decode($new_data['Content']['data'], true);

				return $content_data;

			}

		}else{

			throw new NotFoundException();

		}	

	}
	/* Returns an array of all content. Can choose a subfolder as well. */
	/* Useful for sitemap.xml files */
	public function getAllContentAsList($inFolder = "", $sort = false){

		if(!Configure::check('Statalike.content_folder')) throw new CakeException('Statalike\'s configuration is not set. Please see README.');

		if ($inFolder != "" && !$this->isValidSlug($inFolder)) throw new UnauthorizedException(); // If slug isn't valid, throw an exception

		$contentFolder = Configure::read('Statalike.content_folder').$inFolder;
		$folder = new Folder($contentFolder);
		$allContent = $folder->findRecursive('.*\.md', true);

		if(count($allContent) > 0){

			$contentList = [];

			foreach($allContent as $content){

				$cleanFolder = preg_replace('/\//', '\/', Configure::read('Statalike.content_folder')); // Set up path to use as preg
				$cleanFolder = preg_replace('/\./', '\.', $cleanFolder);			

				$file = preg_replace('/'.$cleanFolder.'/', '', $content);
				$slug = preg_replace("/\.md/", '', $file);

				$parsedPage = $this->getContent($slug);

				//$slug = preg_replace("/\/page\.md/", '', $slug);
				$slug = Content::cleanSlug($slug)['slug'];

				if ($slug == "page") $slug = "/";			

				$contentList[] = array(
					'file' => $file,
					'slug' => $slug,
					'title' => @$parsedPage['yaml']['title'],
					'category' => @$parsedPage['yaml']['category'],
					'date' => @$parsedPage['yaml']['date']
				);

			}

			switch($sort){

				case "date:asc":
					usort($contentList, array('Content','compareDatesForSortAsc'));
					break;

				case "date:desc":
					usort($contentList, array('Content','compareDatesForSortDesc'));
					break;

				case "slug:asc":
					usort($contentList, array('Content','compareSlugsForSortAsc'));
					break;

				case "slug:desc":
					usort($contentList, array('Content','compareSlugsForSortDesc'));
					break;

				case "title:asc":
					usort($contentList, array('Content','compareTitlesForSortAsc'));
					break;

				case "title:desc":
					usort($contentList, array('Content','compareTitlesForSortDesc'));
					break;	

				default:
					break;


			}

			return $contentList;
		
		}else{

			return [];

		}

	}

	/* Returns an array of all content slugs, grouped by category */
	public function getAllContentByCategory($inFolder = "", $sort = 'date:desc'){

		$allContent = $this->getAllContentAsList($inFolder, $sort);

		$contentList = [];

		if(count($allContent) > 0){
			foreach($allContent as $content){

				// Check the category
				if(strlen($content['category']) > 0){
					$contentList[$content['category']][] = $content;
				}

			}

			return $contentList;

		}else{

			return [];

		}

	}

	/* Returns an array of all content slugs within a category */
	public function getAllContentInCategory($category, $inFolder = "", $sort = 'date:desc'){

		$allContent = $this->getAllContentAsList($inFolder, $sort);

		$contentList = [];

		if(count($allContent) > 0){

			foreach($allContent as $content){

				// Check the category

				if($category === null){
					if($content['category'] == ''){
						$contentList[] = $content;
					}
				}else{
					if(strlen($content['category']) > 0 && $content['category'] == $category){
						$contentList[] = $content;
					}
				}

			}

			return $contentList;

		}else{

			return [];

		}

	}

	public function getMostRecentContent($inFolder = ""){

		$allContent = $this->getAllContentAsList($inFolder, 'date:desc');

		return $allContent;

	}

	public static function compareDatesForSortDesc($a, $b) {
	 	
		// Returns by date in descending order. Undated shows last.

		if($a['date'] == '' && $b['date'] == ''){ // If neither are set, sort by the slug
			return strcasecmp($a['slug'], $b['slug']);
		}elseif($b['date'] == ''){ // always less than, undated content comes last
			return -1;
		}elseif($a['date'] == ''){ // greater than
			return 1;
		}

	 	$dateA = date_create($a['date']);
	 	$dateB = date_create($b['date']);

	 	if($dateA < $dateB){
	 		return 1;
	 	}elseif($dateA > $dateB){
	 		return -1;
	 	}else{
	 		return 0;
	 	}

	}

	public static function compareDatesForSortAsc($a, $b) {
	 	
		// Returns by date in descending order. Undated shows last.

		if($a['date'] == '' && $b['date'] == ''){ // If neither are set, sort by the slug
			return strcasecmp($a['slug'], $b['slug']);
		}elseif($b['date'] == ''){ // always less than, undated content comes last
			return -1;
		}elseif($a['date'] == ''){ // greater than
			return 1;
		}

	 	$dateA = date_create($a['date']);
	 	$dateB = date_create($b['date']);

	 	if($dateA > $dateB){
	 		return 1;
	 	}elseif($dateA < $dateB){
	 		return -1;
	 	}else{
	 		return 0;
	 	}

	}

	public static function compareSlugsForSortAsc($a, $b) {
	  return strcasecmp($a['slug'], $b['slug']);
	}

	public static function compareSlugsForSortDesc($a, $b) {
	  return strcasecmp($b['slug'], $a['slug']);
	}

	public static function compareTitlesForSortAsc($a, $b) {
	  return strcasecmp($a['title'], $b['title']);
	}

	public static function compareTitlesForSortDesc($a, $b) {
	  return strcasecmp($b['title'], $a['title']);
	}

}