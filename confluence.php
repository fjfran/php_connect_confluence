<?php
/**
 * Class Confluence
 */
class Confluence
{
    private $confluence_url;
    private $parent_id;

    public function __construct()
    {
        $CI =& get_instance();
        $CI->load->library('curl');
        $this->confluence_url = "https://confluence.domain.com";
        $this->confluence_user = "user_confluence";
        $this->confluence_pass = "!con_pass_957";
        $this->confluence_images_dir = "/Applications/XAMPP/xamppfiles/htdocs/connect_to_confluence/images";//Path to store the images of pages


        $this->parent_id = 123;//ID of the page parent in the space
    }

    public function get_confluence_url()
    {
        return $this->confluence_url;
    }

    public function get_parent_id()
    {
        return $this->parent_id;
    }

    public function init()
    {
        return true;
    }

    /**
     * GET RESULT
     * Function to connect to confluence
     * @param string $url
     * @return array
     */
    public function get_result($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $header = array();
        $header[] = "Authorization: Basic " . base64_encode("user_confluence:!con_pass_957");
        $header[] = "Content-Type: application/json";

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_URL, $url);

        $result = curl_exec( $ch );

        echo json_decode($result, true);
    }

    /**
     * GET CONTENT
     * Function to get the content of page in confluence
     * @param int $page_id
     * @return array
     */
    public function getContent($page_id = null)
    {
        $response = array();
        if($page_id) {

            $url = $this->confluence_url . "/rest/api/content/" . $page_id . "?expand=body.view";

            $page = $this->get_result($url); //Connect to confluence to get the content

            if($page) {

                $this->getAttachements($page_id); //Here we donwload the images of the page

                //Use DOM to modify the url of the images
                $doc = new DOMDocument();
                $doc->loadHTML(mb_convert_encoding($page['body']['view']['value'], 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOWARNING);

                $tags = $doc->getElementsByTagName('img');//Get all img tags
                foreach ($tags as $tag) {
                    $filename = $tag->getAttribute('data-linked-resource-default-alias'); //Get file name
                    $new_src_url = config_item('confluence_images_link') . $page_id . '/' . $filename ; //We add the new url
                    $tag->setAttribute('src', $new_src_url); //Apply the new url
                }
                $response['content'] = $doc->saveHTML();
                $response['title'] = $page['title'];
            }
        }
        return $response;
    }

    /**
     * GET PAGES
     * Function to get the child of a page
     * @param int $page_id
     * @return array
     */
    public function getPages($parent_id = null)
    {
        $response = array();
        if($parent_id) {

            $url = $this->confluence_url . "/rest/api/content/".$parent_id."/child?expand=page";

            $result = $this->get_result($url);

            foreach ($result['page']['results'] as $key => $value){
                $response[$key]['id'] = $value['id'];
                $response[$key]['title'] = $value['title'];
                $response[$key]['labels'] = $this->getLabels($value['id']);
            }
        }
        return $response;
    }

    /**
     * GET ATTACHEMENTS
     * Function to get the attachments of a page
     * @param int $page_id
     * @return void
     */
    public function getAttachements($content_id = null)
    {
        $response = array();
        if($content_id) {

            $url = $this->confluence_url . "/rest/api/content/" . $content_id . "/child/attachment";

            $attachements = $this->get_result($url);

            if($attachements) {

                foreach ($attachements['results'] as $attachement) {
                    $filename = $attachement['title'];
                    $link = $attachement['_links']['download'];

                    if (!file_exists($this->confluence_images_dir . '/'. $content_id . '/'. $filename)) {
                        $this->download_attachement($link,$content_id,$filename);
                    }
                }

            }
        }
    }

    /**
     * GET SPACETREE
     *
     * @param int $page_id
     * @return array
     */
    public function get_spaceTree()
    {
        $spaceTree = $this->createSpaceTree($this->parent_id);

        if($spaceTree) {
            return $spaceTree;
        }

        return false;
    }

    /**
     * Create Space Tree
     * Function to get all pages of a space in confluence
     * @param int $parentId
     * @return array
     */
    function createSpaceTree($parentId) {
        $branch = array();
        $pages = $this->getPages($parentId);
        foreach ($pages as $page) {
            if (isset($page['id'])) {
                $children = $this->createSpaceTree($page['id']);
                if ($children) {
                    $page['nodes'] = $children;
                }
                $branch[] = $page;
                unset($pages[$page['id']]);
            }
        }
        return $branch;
    }

    /**
     * GET LABELS
     * Function to get the labels of a page
     * @param int $content_id
     */
    public function getLabels($content_id = null)
    {
        $response = array();
        if($content_id) {

            $url = $this->confluence_url . "/rest/api/content/".$content_id."/label";

            $result = $this->get_result($url);

            foreach ($result['results'] as $value){
                $response[] = $value['name'];
            }
        }
        return $response;
    }

    /**
     * DOWNLOAD ATTACHEMENT
     * Function to download the images of a page in confluence
     * @param string $link, int $content id, string $filename
     */
    public function download_attachement($link = null,$content_id = null,$filename = null)
    {
        if($link && $content_id && $filename) {
            $url = $this->confluence_url . $link . '&os_username='.$this->confluence_user.'&os_password='.$this->confluence_pass;

            if (!file_exists($this->confluence_images_dir)){
                mkdir($this->confluence_images_dir , 0755, true);
            }

            if (!file_exists($this->confluence_images_dir . '/'. $content_id . '/')) {
                mkdir($this->confluence_images_dir . '/' . $content_id . '/', 0755, true);
            }

            copy($url, $this->confluence_images_dir . '/' . $content_id . '/' . $filename);
        }
    }

    public function __destruct()
    {

    }

}
