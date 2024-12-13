<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\ViewLatte;

use DI\DependencyException;
use DI\NotFoundException;
use Exception;
use Latte\Bridges\Tracy\TracyExtension;
use Latte\Engine;
use Latte\Loaders\FileLoader;
use Modules\View\ViewInterface;
use Modules\View\ViewManager;
use Slim\Http\Response;

class LatteView extends ViewManager implements ViewInterface {

    /**
     * @var Engine
     */
    public Engine $viewer;

    /**
     * @var string
     */
    private string $cssClass = "lazyload";

    /**
     * @var string
     */
    private string  $src = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUg".
    "AAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";

    private string $fileType = ".latte";

    protected array $elementAttr = [
        "img"=>"src",
        "source"=>"srcset"
    ];

    /**
     * @var array
     */
    private static array $loadedPlugins = [];

    /**
     * @return void
     */
    public function loadPlugins(): void {
        $this->viewer->setLocale("de");
        foreach ($this->func as $func){
            $this->viewer->addFunction($func, fn(mixed ...$params) => $this->lazyLoadFunction($func, ...$params));
        }
        foreach ($this->plugins as $name => $plugin){
            $this->viewer->addFunction($name, fn(mixed ...$params) => $this->lazyLoadPlugin($name, $plugin, ...$params));
        }
    }

    /**
     * @param string $func
     * @param mixed ...$params
     * @return mixed
     */
    private function lazyLoadFunction(string $func, mixed ...$params): mixed {
        // Подгружаем и сразу выполняем
        return $func(...$params);
    }

    /**
     * @param string $name
     * @param string $pluginClass
     * @param mixed ...$params
     * @return mixed
     * @throws Exception
     */
    private function lazyLoadPlugin(string $name, string $pluginClass, mixed ...$params): mixed {
        if (!isset(self::$loadedPlugins[$name])) {
            if (class_exists($pluginClass)) {
                // Инициализируем плагин
                $class = method_exists($pluginClass, 'setContainer')
                    ? new $pluginClass($this)
                    : new $pluginClass();
                self::$loadedPlugins[$name] = $class;
            } else {
                throw new Exception("Plugin class $pluginClass not found for $name");
            }
        }
        // Выполняем метод process для плагина
        return self::$loadedPlugins[$name]->process(...$params);
    }

    /**
     * @return mixed|null
     * @throws DependencyException
     * @throws NotFoundException
     */
    protected function getImageModel(): mixed {
        if ($this->getContainer()->has("Image\Model")){
            return $this->getContainer()->get("Image\Model");
        }
        else {
            return null;
        }
    }

    /**
     * @return void
     */
    public function beforInit(): void {
        $this->path = ROOT_DIR.$this->config['template']['path'].DIRECTORY_SEPARATOR.$this->config['template']['name'];
        $this->setLayout($this->config['template']['layout'].$this->fileType);
    }

    /**
     * @return void
     */
    public function initView(): void {
        $this->viewer = new Engine();
        $this->viewer->addExtension(new TracyExtension());
        $this->viewer->setLoader(new FileLoader($this->path));

        /*$cachePath = ROOT_DIR.'cache/template'; // или другой путь, например, /tmp/latte
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0777, true);
        }
        $this->viewer->setLocale("de");
        $this->viewer->setAutoRefresh(false);
        $this->viewer->setTempDirectory($cachePath);*/

        $this->loadPlugins();
    }

    /**
     * @param mixed $layout
     * @return void
     */
    public function setLayout(mixed $layout = ''): void {
        if (!str_contains($layout, $this->fileType)) {
            $layout .= $this->fileType;
        }
        if (is_file($this->path.DIRECTORY_SEPARATOR.$layout)){
            $this->layout = $layout;
        }
    }

    /**
     * @param Response $response
     * @param mixed $template
     * @param array $data
     * @return Response
     */
    public function render(Response $response, mixed $template = '', array $data = []): Response {
        $this->initView();
        $this->setVariables($data);
        $status = 200;

        if (is_file($this->path.DIRECTORY_SEPARATOR.$template.$this->fileType)) {
            $this->template = $template . $this->fileType;
        }
        else {
            $this->template = 'error/404.latte';
            $status = 404;
        }

        $this->setVariables([
            'layout' => $this->getLayout()
        ]);

        $content = $this->viewer->renderToString($this->template, $this->getVariables());

        // Image Lazy Load
        if ($this->config['view']['lazyload']){
            $content = $this->setLazyload($content);
            $content = $this->setLazyload($content, 'source');
        }

        // HTML Compressor
        if ($this->config['view']['compressor']) {
            $content = $this->compress($content);
        }

        $response->write($content);
        return $response->withStatus($status)->withHeader('Content-Type', 'text/html');
    }

    /**
     * @param mixed $template
     * @param array $data
     * @return string
     */
    public function getHtml(mixed $template = '', array $data = []): string {
        $this->initView();
        $this->setVariables($data);
        $this->template=$template.$this->fileType;
        if (str_contains($this->template, 'error')){
            $this->setVariable('layout', 'layout/main.latte');
        }
        return $this->viewer->renderToString($this->template, $this->getVariables());
    }

    /**
     * @param mixed $content
     * @param array $data
     * @param string $tmp_path
     * @return string
     */
    public function getHtmlFromContent(mixed $content, array $data = [], string $tmp_path = "tmp/"): string {
        $template = $tmp_path."tmp_".time();
        file_put_contents($this->path.DIRECTORY_SEPARATOR.$template.$this->fileType, $content);
        $tmp_content = $this->getHtml($template, $data);
        unlink($this->path.DIRECTORY_SEPARATOR.$template.$this->fileType);
        return $tmp_content;
    }

    /**
     * @param Response $response
     * @param mixed $template
     * @param array $data
     * @return Response
     */
    public function fetch(Response $response, mixed $template = '', array $data = []): Response {
        $this->initView();
        $this->setVariables($data);

        if (is_file($this->path.DIRECTORY_SEPARATOR.$template.$this->fileType)) {
            $this->template = $template . $this->fileType;
        }
        else {
            $this->template = 'error/404.latte';
        }

        $content = $this->viewer->renderToString($this->template, $this->getVariables());
        // Image Lazy Load
        if ($this->config['view']['lazyload']){
            $content = $this->setLazyload($content);
            $content = $this->setLazyload($content, "source");
        }

        // HTML Compressor
        if ($this->config['view']['compressor']){
            $content = $this->compress($content);
        }

        $response->write($content);
        return $response;
    }

    /**
     * @param string $content
     * @param string $element
     * @return string
     */
    protected function setLazyload(string $content, string $element = 'img'): string {
        try {
            $imageModel = $this->getImageModel();
        } catch (DependencyException | NotFoundException $e) {
            return $e->getMessage();
        }

        $content = str_replace(["\n", "\r"], "", $content);
        preg_match_all("|<$element(.*)" . $this->elementAttr[$element] . "=\"(.*)\"(.*)>|U", $content, $match);

        foreach ($match[0] as $org_img) {
            $content = str_replace($org_img, $this->processImage($org_img, $element, $imageModel), $content);
        }

        return $content;
    }

    /**
     * @param string $org_img
     * @param string $element
     * @param $imageModel
     * @return string
     */
    private function processImage(string $org_img, string $element, $imageModel): string {
        $tmp_img = $org_img;
        $src_match = $this->extractAttribute($org_img, $this->elementAttr[$element]);
        $class_match = $this->extractAttribute($org_img, "class");
        $class = $this->determineClass($class_match);

        if (!is_null($imageModel)) {
            $src_match[1][0] = $imageModel->getCompressedImage($src_match[1][0], 600);
        }

        if (!str_contains($class, "nolazy")){
            $tmp_img = $this->replaceAttributes($tmp_img, $src_match, $class, $element);
        }
        return str_replace("alt=\"\"", "alt=\"Content Image\"", $tmp_img);
    }

    /**
     * @param string $img
     * @param string $attribute
     * @return array
     */
    private function extractAttribute(string $img, string $attribute): array {
        preg_match_all("|$attribute=\"(.*)\"|U", $img, $match);
        return $match;
    }

    /**
     * @param array $class_match
     * @return string
     */
    private function determineClass(array $class_match): string {
        return !empty($class_match[1][0]) ? $class_match[1][0] . " " . $this->cssClass : $this->cssClass;
    }

    /**
     * @param string $img
     * @param array $src_match
     * @param string $class
     * @param string $element
     * @return string
     */
    private function replaceAttributes(string $img, array $src_match, string $class, string $element): string {
        if ($element === "source") {
            return str_replace(
                $src_match[0][0],
                $this->elementAttr[$element] . "=\"" . $src_match[1][0] . "\"",
                $img
            );
        } else {
            return str_replace(
                $src_match[0][0],
                "data-src=\"" . $src_match[1][0] . "\" src=\"" . $this->src . "\" class=\"$class\" loading='lazy'",
                $img
            );
        }
    }

    /**
     * @param string $content
     * @return string
     */
    private function compress(string $content): string {
         return preg_replace(
            [
                "/\r|\n|\t|\f|\0|\x0B/",     // Убираем переносы строк, табы и спецсимволы
                "/<!--.*?-->/s",             // Убираем HTML-комментарии
                "/>\s+</",                   // Убираем пробелы между HTML-тегами
                "/\s{2,}/"                   // Убираем избыточные пробелы
            ],
            [
                "",
                "",
                "><",
                " "
            ],
            $content
        );
    }
}
