<?php

App::uses('AppController', 'Controller');

class ContentController extends AppController {

	public $content = [];

	public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow('display');

        $this->view = 'default'; // Set up the default view
    }

	public function display($content = null){

		$content_slug = (isset($content)) ? $content : $this->request->params['pass'][0]; // If content is set, get it. Otherwise get it from the passed params
		
		$content_data = $this->Content->getContent($content_slug);

		// Check for layout
		if(isset($content_data['yaml']['_cake_layout'])){

			$this->layout = $content_data['yaml']['_cake_layout']; // Set the layout
			unset($content_data['yaml']['_cake_layout']); // Get rid of variable, the view doesn't need it

		}

		// Check for view
		if(isset($content_data['yaml']['_cake_view'])){

			$this->view = $content_data['yaml']['_cake_view']; // Set the view
			unset($content_data['yaml']['_cake_view']); // Get rid of variable, the view doesn't need it

		}

		$this->set($content_data['yaml']); // Set the variables from the frontmatter for the view
		$this->set('content', $content_data['content']); // Set the markdown content for the view

		$this->render($this->view, $this->layout, Configure::read('Statalike.views_folder'), Configure::read('Statalike.layouts_folder'));			

	}

}
