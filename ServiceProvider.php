<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\ViewLatte;

use Core\Module\Provider;

class ServiceProvider extends Provider {

    protected string $viewManager = "ViewManager::View";

    /**
     * @return void
     */
    public function beforeInit(): void {
        $container = $this->getContainer();
        if (!$container->has($this->viewManager)){
            $view = new LatteView($this);
            $view->registry();
            $view->beforInit();
            $container->set($this->viewManager, $view->initView());
        }
    }

}
