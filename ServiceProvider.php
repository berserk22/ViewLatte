<?php

/**
 * @author Sergey Tevs
 * @email sergey@tevs.org
 */

namespace Modules\ViewLatte;

use Core\Module\Provider;
use DI\DependencyException;
use DI\NotFoundException;

class ServiceProvider extends Provider {

    protected string $viewManager = "ViewManager::View";

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function beforeInit(): void {
        $container = $this->getContainer();
        if (!$container->has($this->viewManager)){
            $container->set($this->viewManager, new LatteView($this));
            $container->get($this->viewManager)->registry();
            $container->get($this->viewManager)->beforInit();
        }
    }

}
