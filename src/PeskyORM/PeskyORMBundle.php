<?php

namespace PeskyORM;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle.
 */
class PeskyORMBundle extends Bundle
{
    /**
     * {@inheritDoc}
     */
    public function boot() {
        if ($this->container->hasParameter('database_name')) {
            DbModel::setDbConnectionConfig(
                DbConnectionConfig::create()
                    ->setDriver($this->container->getParameter('database_driver'))
                    ->setHost($this->container->getParameter('database_host'))
                    ->setDbName($this->container->getParameter('database_name'))
                    ->setUserName($this->container->getParameter('database_user'))
                    ->setPassword($this->container->getParameter('database_password'))
            );
        }
    }

}
