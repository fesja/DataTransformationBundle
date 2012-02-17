<?php
namespace RtxLabs\DataTransformationBundle\Binder;

class Binder implements IBinder {
    private $fields = array();
    private $joins = array();
    private $bind = null;
    private $to = null;

    /**
     * @static
     * @return Binder
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Binds the object defined in 'from' to an object.
     * @throws LogicException
     * @return Object
     */
    public function execute()
    {
        $result = array();

        if (!is_null($this->to)) {
             $result = $this->to;
        }

        //todo: rename from to bind

        if (is_null($this->bind)) {
            $result = null;
        }
        elseif ($this->isIterable($this->bind)) {
            foreach ($this->bind as $item) {
                $result[] = $this->copy()->bind($item)->execute();
            }
        }
        else {
            foreach ($this->fields as $field=>$closure) {
                $value = $closure;
                if (is_null($closure)) {
                    $value = $this->getValue($this->bind, $field);
                }
                elseif (is_callable($closure)) {
                    $value = $closure($this->bind);
                }

                if ($value instanceof IBinder) {
                    $value = $value->execute();
                }

                $result = $this->setValueToFieldOfObject($value, $field, $result);
            }

            foreach ($this->joins as $field => $binder) {
                $result[$field] = $binder->bind($this->getValue($this->bind, $field))->execute();
            }
        }

        return $result;
    }

    /**
     * @param $object
     * @return bool
     */
    private function isIterable($object)
    {
        if (is_array($object) && !$this->isAssocArray($object)) {
            return true;
        }

        if (is_object($object)) {
            $reflection = new \ReflectionObject($object);
            if ($reflection->isIterateable()) {
                return true;
            }
        }

        return false;
    }

    private function isAssocArray($value) {
        return is_array($this->bind) && array_values($value) !== $value;
    }

    /**
     * @param string $field
     * @param closure $closure
     * @return Binder
     */
    public function field($field, $closure=null)
    {
        if (!key_exists($field, $this->fields)) {
            $this->fields[$field] = $closure;
        }

        return $this;
    }

    public function fields($fields) {
        foreach ($fields as $field) {
            $this->field($field);
        }

        return $this;
    }

    /**
     * @param string $field
     * @param \RtxLabs\DataTransformationBundle\Binder\IBinder $binder
     * @return \RtxLabs\DataTransformationBundle\Binder\Binder
     */
    public function join($field, $binder) {
        $this->joins[$field] = $binder;
        return $this;
    }

    /**
     * @param $entity the entity to bind
     * @return Binder
     */
    public function bind($entity) {
        $this->bind = $entity;
        return $this;
    }

    /**
     * @param object $entity
     * @return Binder
     */
    public function to($entity) {
        $this->to = $entity;
        return $this;
    }

    /**
     * @return Binder
     */
    private function copy() {
        $copy = new Binder();
        $copy->fields = $this->fields;
        $copy->joins = $this->joins;
        $copy->bind = $this->bind;

        return $copy;
    }

    /**
     * @param object $object
     * @param string $field
     * @return object
     */
    private function getValue($object, $field) {
        if (is_array($object)) {
            return $object[$field];
        }

        $reflection = new \ReflectionObject($object);

        $getter = $this->findGetter($reflection, $field);
        if ($getter != null) {
            return $getter->invoke($object);
        }

        $property = $this->findProperty($reflection, $field);
        if ($property != null) {
            return $property->getValue($object);
        }

        throw new \LogicException("unknown field: $field in " . get_class($object));
    }

    //todo: rename because object can be an array
    private function setValueToFieldOfObject($value, $field, $object)
    {
        if (is_array($object)) {
            $object[$field] = $value;
        }
        else {
            $reflection = new \ReflectionObject($object);

            $setter = $this->findSetter($reflection, $field);
            if (!is_null($setter)) {
                $setter->invoke($object, $value);
            }

            $property = $this->findProperty($reflection, $field);
            if ($property != null) {
                $property->setValue($object, $value);
            }
        }

        return $object;
    }

    /**
     * @param \ReflectionObject $reflection
     * @param string $field
     * @return null|\ReflectionMethod
     */
    private function findGetter($reflection, $field) {
        $methodName = "get$field";

        if ($reflection->hasMethod($methodName)) {
            return $reflection->getMethod($methodName);
        }

        return null;
    }

    /**
     * @param \ReflectionClass $reflection
     * @param $field
     * @return void
     */
    private function findSetter($reflection, $field) {
        $methodName = "set$field";
        $method = null;

        if ($reflection->hasMethod($methodName)) {
            $method = $reflection->getMethod($methodName);
        }

        if (!is_null($method) &&
            $method->isPublic() &&
            count($method->getParameters()) === 1) {
            return $method;
        }
        else {
            return null;
        }
    }



    /**
     * @param \ReflectionObject $reflection
     * @param string $field
     * @return null|\ReflectionProperty
     */
    private function findProperty($reflection, $field) {
        $property = null;

        if ($reflection->hasProperty($field)) {
            $property = $reflection->getProperty($field);
        }

        if (!is_null($property) && $property->isPublic()) {
            return $property;
        }
        else {
            return null;
        }
    }
}