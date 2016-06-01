<?php
class Tag extends Eloquent
{
	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Tag';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'TagID';
	
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function getList($entityIDs, $entityName = 'receipt', $onlyName = false)
	{
		$tags = DB::table('Tag AS t')
				->join('TagIndex AS ti', 'ti.TagID', '=', 't.TagID')
				->select('EntityID', 'Name', 't.TagID')
				->where('EntityType', $entityName);
		if (! is_array($entityIDs)) {
			$tags->where('EntityID', $entityIDs);
		} else {
			$tags->whereIn('EntityID', $entityIDs);
		}
		
		if ($onlyName) {
			return $tags->lists('Name');
		}
		
		return $tags->get();
	}
	
	public static function saveTags($entityID, $entityType, $newTags = array(), $oldTags = array())
	{
		if (! count($newTags) && ! count($oldTags)) {
			return;
		}
		else if (! count($oldTags)) {
			$diffDelete = array();
			$diffAdd = $newTags;
		} else if (! count($newTags)) {
			$diffDelete = $oldTags;
			$diffAdd = array();
		} else {
			$diffDelete = array_diff($oldTags, $newTags);
			$diffAdd = array_diff($newTags, $oldTags);
		}
		
		if (count($diffAdd)) {                    
			foreach ($diffAdd as $tagName) {
				$tagID = DB::table('Tag')
						->select('TagID')->where('Name', $tagName)
						->pluck('Name');
				
				if (! $tagID) {
					$tagID = DB::table('Tag')
							->insertGetId(array('Name' => $tagName));
				}
				
				DB::table('TagIndex')
						->insert(array(
							'EntityID' => $entityID,
							'EntityType' => $entityType,
							'TagID' => $tagID
						));
			}
		}
		
		if (count($diffDelete)) {
			$tagIDs = DB::table('Tag')
					->select('TagID')->whereIn('Name', $diffDelete)
					->lists('TagID');
			
			DB::table('TagIndex')
					->whereIn('TagID', $tagIDs)
					->where('EntityID', $entityID)
					->where('EntityType', $entityType)
					->delete();
		}
	}
	
	
	
	public static function deleteIndexList($entityIDs, $entityType)
	{
		//Firstly, delete all tag indexes of receipts
		DB::table('TagIndex')
				->whereIn('EntityID', $entityIDs)
				->where('EntityType', $entityType)
				->delete();
	}
}