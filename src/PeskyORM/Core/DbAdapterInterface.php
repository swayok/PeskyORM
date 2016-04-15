<?php

namespace PeskyORM\Core;

interface DbAdapterInterface {

    /**
     * Connect to DB once
     * @return $this
     */
    public function getConnection();

    /**
     * @return $this
     */
    public function disconnect();

    /**
     * Get last executed query
     * @return null|string
     */
    public function getLastQuery();


}