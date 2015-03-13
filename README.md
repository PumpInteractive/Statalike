# Statalike

A Static content plugin for CakePHP using Mardown-formatted text files. Very similar to the content implementation of the [Statamic](http://www.statamic.com) CMS.

## Installation

Copy plugin folder into the app/Plugin directory

## Configuration

### Database Setup

You may need to run this command in the Cake console to create the table for cached Statalike content

    cake schema create --plugin Statalike

### Configuration

Set up Statalike's content folder location in app/Config/bootstrap.php:

    /* Statalike Configuration */
    Configure::write('Statalike.content_folder', APP.'/View/Content/_content/');

### Route Setup

Set up your routes for content. There are a few different ways to configure Statalike:

#### Statalike as a Fallback

To use for all content that isn't a controller set it as the last entry, before the CakePHP default routes. Statalike will return a NotFoundException if the content doesn't exist in your content folder.

    /* Content Routing using Statalike Plugin */
    Router::connect('/**', array('plugin' => 'Statalike', 'controller' => 'content', 'action' => 'display'));
    
#### Statalike for Specific Content

You can also route to to only specific pages

    Router::connect('/', array('plugin' => 'Statalike', 'controller' => 'content', 'action' => 'display', 'page'));

The final 'page' variable above is the slug of the content you want to display.
    
###  View Files

Create custom views for your content by creating the folders below inside your Cake project:

* app/View/Plugin/
    * Statalike
        * Content
            * view.ctp
            * view2.ctp
        * Layouts
            * layout.ctp
            * layout2.ctp

You can then set custom variables in your page's YAML frontmatter to choose the layout or view using:

    _cake_layout: layout
    _cake_view: view

#### Use Project Layouts

To use your site's layouts, create a layout file containing a PHP require() method call for the layout you want to use. This is created in the app/View/Plugin/Statalike/Layouts/ folder:

    <?php 

    // Use the default layout
    require(ROOT.DS.APP_DIR.'/View/Layouts/default.ctp');

    ?>