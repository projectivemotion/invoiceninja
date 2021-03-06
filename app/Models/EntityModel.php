<?php namespace App\Models;

use Auth;
use Eloquent;
use Utils;

class EntityModel extends Eloquent
{
    public $timestamps = true;
    protected $hidden = ['id'];

    public static function createNew($context = null)
    {
        $className = get_called_class();
        $entity = new $className();

        if ($context) {
            $entity->user_id = $context instanceof User ? $context->id : $context->user_id;
            $entity->account_id = $context->account_id;
        } elseif (Auth::check()) {
            $entity->user_id = Auth::user()->id;
            $entity->account_id = Auth::user()->account_id;
        } else {
            Utils::fatalError();
        }

        $lastEntity = $className::withTrashed()
                        ->scope(false, $entity->account_id)
                        ->orderBy('public_id', 'DESC')
                        ->first();

        if ($lastEntity) {
            $entity->public_id = $lastEntity->public_id + 1;
        } else {
            $entity->public_id = 1;
        }

        return $entity;
    }

    public static function getPrivateId($publicId)
    {
        $className = get_called_class();

        return $className::scope($publicId)->withTrashed()->pluck('id');
    }

    public function getActivityKey()
    {
        return '[' . $this->getEntityType().':'.$this->public_id.':'.$this->getDisplayName() . ']';
    }

    /*
    public function getEntityType()
    {
        return '';
    }

    public function getNmae()
    {
        return '';
    }
    */

    public function scopeScope($query, $publicId = false, $accountId = false)
    {
        if (!$accountId) {
            $accountId = Auth::user()->account_id;
        }

        $query->where($this->getTable() .'.account_id', '=', $accountId);

        if ($publicId) {
            if (is_array($publicId)) {
                $query->whereIn('public_id', $publicId);
            } else {
                $query->wherePublicId($publicId);
            }
        }

        return $query;
    }

    public function getName()
    {
        return $this->public_id;
    }

    public function getDisplayName()
    {
        return $this->getName();
    }

    public function setNullValues()
    {
        foreach ($this->fillable as $field) {
            if (strstr($field, '_id') && !$this->$field) {
                $this->$field = null;
            }
        }
    }

    // converts "App\Models\Client" to "client_id"
    public function getKeyField()
    {
        $class = get_class($this);
        $parts = explode('\\', $class);
        $name = $parts[count($parts)-1];
        return strtolower($name) . '_id';
    }
}
