<?php

abstract class BaseModel extends Eloquent
{
    public static function getAll (array $where = array(), array $sort = array(), $limit = 10, $offset = 0) {
        $query = DB::table(static::$_table . ' as r');
        
        static::onPreQuery($query, $where);
        
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $query->whereIn('r.' . $key, $value);    
            } else {
                $query->where('r.' . $key, $value);
            }
        }
        
        foreach ($sort as $key => $value) {
            $query->orderBy('r.' . $key, $value);    
        }
        
        if ($limit) {
            $query->skip($offset);
            $query->take($limit);
        }
        
        return $query->get();
    }
    
    public static function countAll (array $where = array()) {
        $query = DB::table(static::$_table . ' as r');
        
        static::onPreQuery($query, $where);
        
        foreach ($where as $key => $value) {
            $query->where('r.' . $key, $value);    
        }

        return $query->count();
    }
    
    public static function getById ($id)
    {
        if (is_array($id)) {
            return static::getAll(array(static::$_primaryKey => $id));
        }
        
        $query = DB::table(static::$_table . ' as r');
        
        static::onPreQuery($query);
        
        $query->where('r.' . static::$_primaryKey, $id);
        
        return $query->first();
    }
    
    public static function deleteById ($id) {
        
    }
    
    public static function getPrimaryKey() {
        return static::$_primaryKey;
    }
    
    public static function onPreQuery(\Illuminate\Database\Query\Builder  $query, &$where = null)
    {
        
    }
}
