<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.pm_content_gallery
 *
 * @copyright
 * @license     GNU/Public
 */
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class PlgContentPm_content_gallery extends CMSPlugin
{
    public function resizeImage($file, $w, $h)
    {
        // Detectar o tipo de imagem
        $info = getimagesize($file);
        $mime = $info['mime'];

        // Escolher a função correta para criar a imagem com base no tipo
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $src = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($file);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($file);
                break;
            case 'image/bmp':
                $src = imagecreatefrombmp($file);
                break;
            case 'image/jpg':
                $src = imagecreatefromjpeg($file);
                break;
            default:
                throw new \Exception("Unsupported image format: $mime");
        }

        // Continuar com o redimensionamento
        list($width, $height) = $info;
        $r                    = $width / $height;
        if ($w / $h > $r) {
            $newwidth  = $h * $r;
            $newheight = $h;
        } else {
            $newheight = $w / $r;
            $newwidth  = $w;
        }

        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        // Retornar a imagem redimensionada
        return $dst;
    }
    public function cropImage($file, $size)
    {
        // Detectar o tipo de imagem
        $info = getimagesize($file);
        $mime = $info['mime'];

        // Escolher a função correta para criar a imagem com base no tipo
        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($file);
                break;
            case 'image/png':
                $src = imagecreatefrompng($file);
                break;
            case 'image/gif':
                $src = imagecreatefromgif($file);
                break;
            case 'image/webp':
                $src = imagecreatefromwebp($file);
                break;
            case 'image/bmp':
                $src = imagecreatefrombmp($file);
                break;
            case 'image/jpg':
                $src = imagecreatefromjpeg($file);
                break;
            default:
                throw new \Exception("Unsupported image format: $mime");
        }

        // Continuar com o crop
        list($width, $height) = $info;
        $minSide              = min($width, $height);
        $srcX                 = ($width - $minSide) / 2;
        $srcY                 = ($height - $minSide) / 2;

        $dst = imagecreatetruecolor($size, $size);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $size, $size, $minSide, $minSide);

        // Retornar a imagem cortada
        return $dst;
    }

    public function onContentPrepare($context, &$article, &$params, $limitstart)
    {
        $customTagName = $this->params->get("customtagname", "pmgallery");

        // Simple performance check to determine whether plugin should process further
        if (strpos($article->text, '{' . $customTagName) === false) {
            return;
        }

        // Don't run this plugin when the content is being indexed by com_finder
        if ($context === 'com_finder.indexer') {
            $article->text = preg_replace('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', '', $article->text);
            return;
        }

        preg_match_all('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', $article->text, $matches);
        $allMatches = $matches[0];

        if (empty($allMatches)) {
            return;
        }

        // We need to parse all matches first to determine if we need to load assets based on tag overrides
        $forceLoadOwl = false;

        $parsedTagsParams = [];
        foreach ($allMatches as $m => $match) {
            preg_match_all('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', $match, $newmatches);
            $newMatchesContent[$m] = $newmatches[1][0] ?? '';
            $contentArray          = explode("|", $newMatchesContent[$m]);
            $pasta                 = isset($contentArray[0]) ? $contentArray[0] : '';

            // Clone original plugin parameters so each tag operates independently
            $localParams = clone $this->params;

            // Apply overrides from the tag
            foreach ($contentArray as $param) {
                if (strpos($param, "=") !== false) {
                    list($key, $value) = explode("=", $param);
                    $localParams->set($key, $value);
                }
            }

            // Check if ANY tag explicitly requests or defaults to owl_carousel
            if ($localParams->get("gallery_type", "owl_carousel") == "owl_carousel") {
                $forceLoadOwl = true;
            }

            $parsedTagsParams[$m] = [
                'match'                => $match,
                'contentArray'         => $contentArray,
                'contentWithoutParams' => preg_replace('/\b' . preg_quote($param, '/') . '\b/', '', $newMatchesContent[$m]),
                'pasta'                => $pasta,
                'params'               => $localParams,
            ];
        }

        $doc            = Factory::getDocument();
        $isHtmlDocument = $doc instanceof \Joomla\CMS\Document\HtmlDocument  || $doc->getType() === 'html';

        if ($isHtmlDocument) {
            $loadJquery    = $this->params->get('load_jquery', '1');
            $loadBootstrap = $this->params->get('load_bootstrap', '1');
            $loadOwl       = $this->params->get('load_owl', '1');

            if (version_compare(JVERSION, '4.0', '>=')) {
                if (method_exists($doc, 'getWebAssetManager')) {
                    $wa = $doc->getWebAssetManager();

                    if ($loadJquery == '1') {
                        if (! $wa->assetExists('script', 'joomla.jquery')) {
                            $wa->registerAndUseScript('joomlajquery', Uri::root(true) . 'media/vendor/jquery/js/jquery.min.js', ['version' => 'auto', 'relative' => true]);
                        }
                        if (! $wa->assetExists('script', 'joomla.jquery-migrate')) {
                            $wa->registerAndUseScript('joomla.jquery-migrate', Uri::root(true) . 'media/vendor/jquery-migrate/js/jquery-migrate.min.js', ['version' => 'auto', 'relative' => true]);
                        }
                    }

                    if ($loadBootstrap == '1') {
                        // check if exists bootstrap in the template and if not, load it
                        if (! $wa->assetExists('script', 'bootstrap')) {
                            $wa->registerAndUseScript('bootstrap.bundle', 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/js/bootstrap.bundle.min.js', [], ['defer' => true]);
                            // inser script declaration
                            $wa->registerAndUseInlineScript('modal.active', 'jQuery(document).ready(function($) {
                            $(".modal").on("shown.bs.modal", function () {
                                console.log("Modal shown");
                                });
                            });', ['defer' => true]);
                        }
                        if (! $wa->assetExists('style', 'bootstrap.css')) {
                            $wa->registerAndUseStyle('bootstrap.css', 'https://cdn.jsdelivr.net/npm/bootstrap@latest/dist/css/bootstrap.min.css');
                        }
                    }

                    if ($forceLoadOwl && $loadOwl == '1') {
                        $wa->registerAndUseStyle('pm_content_gallery_default', Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/owl.theme.default.min.css', ['version' => 'auto', 'relative' => true]);
                        $wa->registerAndUseStyle('pm_content_gallery_carousel', Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/owl.carousel.min.css', ['version' => 'auto', 'relative' => true]);
                        $wa->registerAndUseScript('pm_content_gallery_js', Uri::root(true) . 'plugins/content/pm_content_gallery/assets/js/owl.carousel.min.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
                    }
                    $wa->registerAndUseStyle('pm_content_gallery_temabasico', Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/temabasico.css', ['version' => 'auto', 'relative' => true]);
                }
            } else {
                if ($forceLoadOwl && $loadOwl == '1') {
                    $doc->addStyleSheet(Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/owl.theme.default.min.css');
                    $doc->addStyleSheet(Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/owl.carousel.min.css');
                    $doc->addScript(Uri::root(true) . 'plugins/content/pm_content_gallery/assets/js/owl.carousel.min.js', ['version' => 'auto', 'relative' => true], ['defer' => true]);
                }
                $doc->addStyleSheet(Uri::root(true) . 'plugins/content/pm_content_gallery/assets/css/temabasico.css');

                if ($loadJquery == '1') {
                    HTMLHelper::_('jquery.framework', true, true);
                }
            }
        }

        $html       = [];
        $modalsHtml = ""; // String to store modals HTML separately

        foreach ($parsedTagsParams as $m => $tagData) {
            $contentArray = $tagData['contentArray'];
            $pasta        = $tagData['pasta'];
            $localParams  = $tagData['params'];

            $directory = $localParams->get('folder', 'images') . '/' . $pasta;
            $descricao = $localParams->get("description", "");

            if (! is_dir($directory)) {
                $app = Factory::getApplication();
                $app->enqueueMessage(Text::sprintf('Directory not found: %s', $directory), 'error');
                $article->text = preg_replace('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', '', $article->text, 1);
                continue;
            }

            $files = preg_grep('~\.(jpeg|jpg|png|webp|gif|JPEG|JPG|PNG|WEBP|GIF)$~', scandir($directory));
            if (empty($files)) {
                $article->text = preg_replace('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', '', $article->text, 1);
                continue;
            }

            $html[$m]  = '';
            $html[$m] .= '<section class="pmcontentgallery gallery-' . $article->id . $m . '">';
            // Obtem o tipo de galeria deste shortcode específico
            $galleryType  = $localParams->get("gallery_type", "owl_carousel");

// Inicializa a variável de classes e atributos
            $carouselClass      = '';
            $carouselAttributes = '';

// Configurações baseadas no tipo de galeria
            switch ($galleryType) {
                case "owl_carousel":
                    $carouselClass = 'owl-carousel carrossel-' . $article->id . $m . ' owl-theme';
                    break;

                case "bootstrap_carousel":
                    $carouselClass      = 'carousel slide';
                    $carouselAttributes = ' id="carousel-' . $article->id . $m . '" data-bs-ride="carousel"';
                    break;

                case "masonry":
                    $imagesPerRow       = (int) $localParams->get("images_per_row", 3);
                    $imagesPerRow       = $imagesPerRow > 0 ? $imagesPerRow : 1;
                    $carouselClass      = 'masonry-gallery masonry-' . $article->id . $m;
                    $carouselAttributes = ' style="column-count: ' . $imagesPerRow . '; column-gap: 1rem;"';
                    break;

                default:
                    $carouselClass = 'row';
                    break;
            }

// Monta o HTML principal
            $html[$m] .= '<div class="description"><h3 class="gallery-title">' . $descricao . '</h3></div>';
            $html[$m] .= '<div class="' . $carouselClass . '"' . $carouselAttributes . '>';

// Adiciona a div carousel-inner apenas para bootstrap_carousel
            if ($galleryType == "bootstrap_carousel") {
                $html[$m] .= '<div class="carousel-indicators">';
                foreach ($files as $k => $file) {
                    // Corrigido o índice para o indicador de slide
                    $html[$m] .= '<button type="button" data-bs-target="#carousel-' . $article->id . $m . '" data-bs-slide-to="' . $k - 2 . '" ' . ($k == 2 ? 'class="active" aria-current="true"' : '') . ' aria-label="Slide ' . ($k - 1) . '"></button>';
                }
                $html[$m] .= '</div>';
                $html[$m] .= '<div class="carousel-inner">';

            }
            foreach ($files as $k => $file) {
                $imagem    = Uri::base() . $directory . '/' . $file;
                $heightb4  = $localParams->get("height", "16by9");
                $heightb5  = str_replace("by", "x", $heightb4);
                $imageName = pathinfo($file, PATHINFO_FILENAME);
                $imageName = str_replace(["-", "_"], " ", $imageName);
                $imageName = ucwords($imageName);
                $alt       = $descricao ? $descricao . ' - ' . ($k - 1) : $imageName;
                // check image is portrait or landscape
                $image            = getimagesize($directory . '/' . $file);
                $imageWidth       = $image[0];
                $imageHeight      = $image[1];
                $thumbnailWidth   = $localParams->get("thumbnail_width", "300");
                $thumbnailHeight  = $localParams->get("thumbnail_height", "300");
                $imageRatio       = $imageWidth / $imageHeight;
                $imageOrientation = $imageRatio > 1 ? 'landscape' : 'portrait';
                // generate thumbnail image
                $thumbnail = $directory . '/thumbnail/' . pathinfo($file, PATHINFO_FILENAME) . '_' . $thumbnailWidth . 'x' . $thumbnailHeight . '.' . pathinfo($file, PATHINFO_EXTENSION);
                if (! file_exists($directory . '/thumbnail')) {
                    mkdir($directory . '/thumbnail', 0777, true);
                }
                if (! file_exists($thumbnail)) {
                    // use joomla default image resize function
                    // create function to resize image
                    $image = $this->cropImage($directory . '/' . $file, $thumbnailWidth, $thumbnailHeight);
                    // save image
                    imagejpeg($image, $thumbnail);
                }
                // End of thumbnail generation logic
                if ($galleryType == "bootstrap_carousel") {
                    $itemClass = 'carousel-item ' . ($k == 2 ? 'active' : '');
                } elseif ($galleryType == "owl_carousel") {
                    $itemClass = 'item item-' . $article->id . $m . '-' . $k;
                } elseif ($galleryType == "masonry") {
                    $itemClass = 'item masonry-item mb-3';
                } else {
                    $imagesPerRow = (int) $localParams->get("images_per_row", 3);
                    $imagesPerRow = $imagesPerRow > 0 ? $imagesPerRow : 1;
                    $itemClass    = 'item col-md-' . round(12 / $imagesPerRow) . ' mb-3';
                }

                $html[$m] .= '<div class="' . $itemClass . '"' . ($galleryType == "masonry" ? ' style="break-inside: avoid;"' : '') . '>';

                $ratioClass  = ($galleryType == "masonry") ? '' : ' ratio ratio-' . $heightb5 . ' ' . $imageOrientation;
                $html[$m]   .= '<div class="overflow-hidden embed-responsive embed-responsive-' . $heightb4 . $ratioClass . '">';
                if ($localParams->get("modal", "1") == "1") {
                    $html[$m] .= '<div class="modal-toggle" data-bs-toggle="modal" data-bs-target="#galleryModal-' . $article->id . '-' . $m . '-' . $k . '" rel="gallery-' . $article->id . '-' . $m . '" gallery="' . $article->id . '-' . $m . '">';
                }
                if ($localParams->get("gallery_type", "grid") == "grid") {
                    $html[$m] .= HTMLHelper::_('image', $thumbnail, $alt, ['class' => 'embed-responsive-item img-fluid']);
                } else {
                    $html[$m] .= HTMLHelper::_('image', $imagem, $alt, ['class' => 'embed-responsive-item img-fluid']);
                }
                if ($localParams->get("show_name", "") == "show_image_name") {
                    $html[$m] .= '<div class="carousel-caption d-none d-md-block">';
                    $html[$m] .= '<p>' . $imageName . '</p>';
                    $html[$m] .= '</div>';
                }if ($localParams->get("show_name", "") == "show_album_name_sequence") {
                    $html[$m] .= '<div class="carousel-caption d-none d-md-block">';
                    $html[$m] .= '<p>' . $alt . '</p>';
                    $html[$m] .= '</div>';
                }
                if ($localParams->get("modal", "1") == "1") {
                    $html[$m] .= '</div>';
                }
                $html[$m] .= '</div>';
                $html[$m] .= '</div>';
            }

            if ($localParams->get("gallery_type", "owl_carousel") == "bootstrap_carousel") {
                $html[$m] .= '</div>';
                $html[$m] .= '<button class="carousel-control-prev" type="button" data-bs-target="#carousel-' . $article->id . $m . '" data-bs-slide="prev">';
                $html[$m] .= '<span class="carousel-control-prev-icon" aria-hidden="true"></span>';
                $html[$m] .= '<span class="visually-hidden">Previous</span>';
                $html[$m] .= '</button>';
                $html[$m] .= '<button class="carousel-control-next" type="button" data-bs-target="#carousel-' . $article->id . $m . '" data-bs-slide="next">';
                $html[$m] .= '<span class="carousel-control-next-icon" aria-hidden="true"></span>';
                $html[$m] .= '<span class="visually-hidden">Next</span>';
                $html[$m] .= '</button>';
            }
            $html[$m] .= '</div>';

            $html[$m] .= '</section>';
            if ($localParams->get("gallery_type", "owl_carousel") == "owl_carousel") {

                $enablenav  = $localParams->get("nav", "true") ? 'true' : 'false';
                $loop       = $localParams->get("loop", "true") ? 'true' : 'false';
                $autoplay   = $localParams->get("autoplay", "true") ? 'true' : 'false';
                $dots       = $localParams->get("dots", "true") ? 'true' : 'false';
                $lazyLoad   = $localParams->get("lazyload", "true") ? 'true' : 'false';
                $html[$m]  .= '<script>
                jQuery(document).ready(function($){
                    $(".carrossel-' . $article->id . $m . '").owlCarousel({
                        loop:' . $loop . ',
                        autoplay: ' . $autoplay . ',
                        margin:' . $localParams->get("margin", "10") . ',
                        nav:' . $enablenav . ',
                        dots:' . $dots . ',
                        dotsEach: ' . $localParams->get("dotseach", "1") . ',
                        lazyLoad: ' . $lazyLoad . ',
                        // nav text
                        navText: ["<i class=\'fas fa-chevron-left\'></i>", "<i class=\'fas fa-chevron-right\'></i>"],
                        responsive:{
                            0:{
                                items: 1
                            },
                            767:{
                                items: ' . round($localParams->get("images_per_row") / 2) . '
                            },
                            1000:{
                                items:' . $localParams->get("images_per_row") . '
                            }
                        }
                    });
                });
            </script>';
            } elseif ($localParams->get("gallery_type", "owl_carousel") == "bootstrap_carousel") {
                $html[$m] .= '<script>
                jQuery(document).ready(function($){
                    $("#carousel-' . $article->id . $m . '").carousel({
                        interval: ' . $localParams->get("interval", "5000") . ',
                    });
                });
            </script>';
            }

            //
            // Remover a string do parâmetro para não aparecer no conteúdo renderizado
            $contentWithoutParams = preg_replace('/\b' . preg_quote($param, '/') . '\b/', '', $newMatchesContent[$m]);

            $article->text = preg_replace('@{' . $customTagName . '}(.*){/' . $customTagName . '}@Us', $html[$m] ?? '', $article->text, 1);

            // Clean up original tags safely
            foreach ($contentArray as $param) {
                if (strpos($param, "=") !== false) {
                    list($key, $value) = explode("=", $param);
                    $article->text     = str_replace($key . '=' . $value, '', $article->text);
                }
            }
        }

        // Loop a second time just to append Modals at the end of the text for all local overrides.
        // We reuse the pre-parsed parameters so the image references match exactly.
        foreach ($parsedTagsParams as $m => $tagData) {
            $localParams = $tagData['params'];
            $pasta       = $tagData['pasta'];
            $directory   = $localParams->get('folder', 'images') . '/' . $pasta;
            $descricao   = $localParams->get("description", "");

            if (! is_dir($directory)) {
                continue;
            }

            $files = preg_grep('~\.(jpeg|jpg|png|webp|gif|JPEG|JPG|PNG|WEBP|GIF)$~', scandir($directory));
            if (empty($files)) {
                continue;
            }

            if ($localParams->get("modal", "1") == "1") {

                $modalsHtml = '<div class="pm-modal-gallery pm-modal-gallery-' . $article->id . ' position-relative">';
                foreach ($files as $k => $file) {

                    $imagem      = Uri::base() . $directory . '/' . $file;
                    $heightb4    = $localParams->get("height", "16by9");
                    $heightb5    = str_replace("by", "x", $heightb4);
                    $imageName   = pathinfo($file, PATHINFO_FILENAME);
                    $imageName   = str_replace(["-", "_"], " ", $imageName);
                    $imageName   = ucwords($imageName);
                    $alt         = $descricao ? $descricao . ' - ' . $k : $imageName;
                    $modalsHtml .= '<div class="modal fade" id="galleryModal-' . $article->id . '-' . $m . '-' . $k . '" tabindex="-1" aria-labelledby="galleryModalLabel-' . $article->id . '-' . $m . '-' . $k . '" aria-hidden="true">';
                    $modalsHtml .= '<div class="modal-dialog modal-dialog-centered modal-xl">';

                    $modalsHtml .= '<div class="modal-content">';
                    $modalsHtml .= '<div class="modal-header">';
                    // hidde title if show_name is not show_image_name
                    if ($localParams->get("show_name", "")) {
                        $modalsHtml .= '<h5 class="modal-title" id="galleryModalLabel-' . $article->id . '-' . $m . '-' . $k . '">' . ($localParams->get("show_name", "") == "show_album_name_sequence" ? $alt : $imageName) . '</h5>';
                    }
                    $modalsHtml .= '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>';
                    $modalsHtml .= '</div>';
                    $modalsHtml .= '<div class="modal-body position-relative">';
                    $modalsHtml .= HTMLHelper::_('image', $imagem, $alt, ['class' => 'img-fluid']);
                    // Botão Previous
                    $modalsHtml .= '<button class="btn btn-secondary btn-prev" data-bs-target="#galleryModal-' . $article->id . '-' . $m . '-' . ($k - 1) . '" data-bs-toggle="modal" ' . ($k == 2 ? 'disabled' : '') . '><i class="fas fa-chevron-left"></i></button>';
                    // Botão Next
                    $modalsHtml .= '<button class="btn btn-secondary btn-next" data-bs-target="#galleryModal-' . $article->id . '-' . $m . '-' . ($k + 1) . '" data-bs-toggle="modal" ' . ($k == count($files) + 1 ? 'disabled' : '') . '><i class="fas fa-chevron-right"></i></button>';

                    $modalsHtml .= '</div>';
                    $modalsHtml .= '</div>';
                    $modalsHtml .= '</div>';
                    $modalsHtml .= '</div>';

                }
                $modalsHtml .= '</div>';
            }
            // Append modals HTML to the end of article text
            $article->text .= $modalsHtml;
        }
    }

    public function onContentAfterDisplay($context, &$article, &$params, $page = 0)
    {
    }
    public function onContentBeforeSave($context, $article, $isNew)
    {
    }
    public function onContentAfterSave($context, $article, $isNew)
    {
    }
    public function onContentPrepareForm($form, $data)
    {
    }
    public function onContentPrepareData($context, $data)
    {
    }
    public function onContentBeforeDelete($context, $data)
    {
    }
    public function onContentAfterDelete($context, $data)
    {
    }
    public function onContentChangeState($context, $pks, $value)
    {
    }
    public function onContentSearchAreas()
    {
    }
    public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
    {
    }
}
