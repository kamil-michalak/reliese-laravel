<?php

/**
 * Created by Cristian.
 * Date: 05/09/16 11:27 PM.
 */

namespace Reliese\Coders\Model;

interface Relation
{
    /**
     * @return string
     */
    public function hint();

    /**
     * @return string
     */
    public function name();

    /**
     * An alternate name used to disambiguate this relation when another
     * relation already claimed its default name (e.g. two foreign keys
     * pointing to the same related table).
     *
     * @return string
     */
    public function disambiguatedName();

    /**
     * @return string
     */
    public function body();

    /**
     * @return string
     */
    public function returnType();
}
