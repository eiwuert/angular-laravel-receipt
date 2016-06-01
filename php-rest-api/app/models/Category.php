<?php
class Category extends BaseModel
{

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'Category';
    
    protected static $_table = 'Category';
	
	/**
	 * The primary key for the model.
	 *
	 * @var string
	 */
	protected $primaryKey = 'CategoryID';
	
    protected static $_primaryKey = 'CategoryID';
    
	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;
	
	public static function fetchAll()
	{
		$categories = DB::table('Category')
				->select('CategoryID', 'Name', 'App', 'Parent')
				->orderBy('App')->orderBy('CategoryOrder')->get();
		
		$categoryReturnList = array();
		
		if (count($categories)) {
			$currentApp = '';
			$currentAppKey = -1;
			
			foreach ($categories as $category) {
				if ($currentApp != $category->App) {
					$currentApp = $category->App;
					$currentAppKey++;
					$categoryReturnList[$currentAppKey] = new stdClass();
					$categoryReturnList[$currentAppKey]->App = new stdClass();
					$categoryReturnList[$currentAppKey]->App->MachineName = $currentApp;
					$categoryReturnList[$currentAppKey]->App->AbbrName = self::getAppAbrr($currentApp);
					$categoryReturnList[$currentAppKey]->App->Name = self::getAppName($currentApp);
					$categoryReturnList[$currentAppKey]->Categories = array();
					
				}
				
				$_category = clone $category;
				unset($_category->App);
				$categoryReturnList[$currentAppKey]->Categories[] = $_category;
			}
		}
		
		return $categoryReturnList;
	}
	
	/**
	 * 
	 */
	public static function getListByApp($app, $userID, $dateFrom = 0, $dateTo = 0, $tripID = 0)
	{
		$categoryQuery = DB::table('Category AS c')
				->select('c.CategoryID', 'c.Name', 'c.Parent', DB::raw('SUM(ca.Amount) AS Amount'))
				->leftJoin('CategoryAmountPerMonth AS ca', function($join) use ($userID, $dateFrom, $dateTo, $app, $tripID) {
					$join->on('ca.CategoryID', '=', 'c.CategoryID')
						->on('UserID', '=', DB::raw($userID));
					
					if (! $tripID) {
						$join->on('ca.Date', '>=', DB::raw($dateFrom));
						
						if ($dateTo) {
							$join->on('ca.Date', '<=', DB::raw($dateTo));
						}
					} else if ($app == 'travel_expense') {
						$join->on('ca.TripID', '=', DB::raw($tripID));
					}
				});
		
		$categoryQuery
				->where('App', $app)
				->groupBy('c.CategoryID')
				->orderBy('CategoryOrder');
		
		$categories = $categoryQuery->get();

		if (count($categories)) {
			$categoryIDs = $categoryQuery->lists('CategoryID');
			
			$itemQuery = DB::table('Item AS i');
			
			if ($app == 'travel_expense' && $tripID) {
				$itemQuery->join('TripItem AS ti', 'ti.TripItemID', '=', 'i.ItemID')
						->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
						->select('i.*', 'PurchaseTime', 'r.MerchantID')
						->whereIn('CategoryID', $categoryIDs)
						->where('ti.TripID', $tripID)
						->where('i.IsJoined', 0);
			} else {
				$itemQuery->join('Receipt AS r', 'r.ReceiptID', '=', 'i.ReceiptID')
						->select('i.*', 'PurchaseTime', 'r.MerchantID')
						->whereIn('CategoryID', $categoryIDs)
						->where('r.UserID', $userID)
						->where('i.IsJoined', 0);
                
                if ($dateFrom) {
                    $itemQuery->where('ExpensePeriodFrom', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $itemQuery->where('ExpensePeriodFrom', '<=', $dateTo);
                }
			}
			
            $itemQuery->orderby('PurchaseTime', 'DESC');
                
			$itemIDs = $itemQuery->lists('ItemID');
			$items = $itemQuery->get();

            //Add merchant name for item
            foreach ($items as $item) {
                $item->MerchantName = Merchant::getName($item->MerchantID);
            }
			
			//We only query to get attachments for 1 time, then use a temp array to add attachments to the right items
			$tmpAttachments = array();
			//We only query to get tags for 1 time, then use a temp array to add tags to the right items
			$tmpTags = array();
			if (count($itemIDs)) {
				//Get attachments of items in the list
				$attachmentList = File::getListByEntities($itemIDs, 'receipt_item');
				//Add from query result to temp array
				if (count($attachmentList)) {
					foreach ($attachmentList as $attachment) {
						$tmpAttachments[$attachment->EntityID][] = $attachment;
					}
				}
				
				//Get tags of items in the list
				$tagList = Tag::getList($itemIDs, 'receipt_item');
				//Add from query result to temp array
				if (count($tagList)) {
					foreach ($tagList as $tag) {
						$tmpTags[$tag->EntityID][] = $tag->Name;
					}
				}
			}
			
			if (count($items)) {
				$tmpItems = array();
				foreach ($items as $item) {
					Item::buildItemMore($item, $tmpTags);					
					if (isset($tmpAttachments[$item->ItemID])) {
						$item->Attachments = $tmpAttachments[$item->ItemID];
					} else {
						$item->Attachments = array();
					}
					
					$item->ExpensePeriodFrom = date('Y-m-d\TH:i:s.B\Z', $item->ExpensePeriodFrom);
					$item->PurchaseTime = date('Y-m-d\TH:i:s.B\Z', $item->PurchaseTime);
					
					$tmpItems[$item->CategoryID][] = $item;
				}
			}
			
			foreach ($categories as $category) {
				if (isset($tmpItems[$category->CategoryID])) {
					$category->Items = $tmpItems[$category->CategoryID];
				} else {
					$category->Items = array();
				}
			}
			
			$categories = self::buildTree($categories);
		}
		
		return $categories;
	}
	
	public static function getAnalyticsList($app, $userID, $dateFrom, $dateTo = null)
	{
		$categories = DB::table('Category AS c')
				->select('c.CategoryID', 'Name', DB::raw('SUM(Amount) AS Amount'))
				->leftJoin('CategoryAmountPerMonth AS ca', function($join) use ($userID, $dateFrom, $dateTo) {
					$join->on('ca.CategoryID', '=', 'c.CategoryID')
						->on('UserID', '=', DB::raw($userID))
						->on('ca.Date', '>=', DB::raw($dateFrom));
					
					if ($dateTo) {
						$join->on('ca.Date', '<=', DB::raw($dateTo));
					}
				})
				->where('App', $app)->where('Parent', 0)
				->orderBy('CategoryOrder')
				->groupBy('c.CategoryID')
				->having(DB::raw('SUM(Amount)'), '>', 0)->get();
		
		$months = 0;
		if ($dateTo) {
			$months = ((date('Y', $dateTo) - date('Y', $dateFrom)) * 12) + (date('n', $dateTo) - date('n', $dateFrom));
		}
        $totalAmount = 0;
		foreach ($categories as $category) {
			if (! $category->Amount) {
				$category->Amount = 0;
			}
            $totalAmount = $totalAmount + $category->Amount;
			if ($months) {
				$category->AverageAmount = number_format($category->Amount / ($months+1), 2, '.', '');
			} else {
				$category->AverageAmount = number_format($category->Amount, 2, '.', '');
			}
		}
		
		return array(
			'Months' => $months + 1,
			'Categories' => $categories,
            'TotalAmount' => $totalAmount
		);
	}
	
	public static function getAnalyticsListByMonth($app, $categoryID, $userID, $dateFrom, $dateTo)
	{
		$categories = DB::table('Category AS c')
				->select('c.CategoryID', 'Date', 'Name', DB::raw('SUM(Amount) AS Amount'))
				->leftJoin('CategoryAmountPerMonth AS ca', function($join) use ($userID, $dateFrom, $dateTo) {
					$join->on('ca.CategoryID', '=', 'c.CategoryID')
						->on('UserID', '=', DB::raw($userID))
						->on('ca.Date', '>=', DB::raw($dateFrom))
						->on('ca.Date', '<=', DB::raw($dateTo));
				})
				->where('c.App', $app)
				->where('c.CategoryID', $categoryID)
				->where('Parent', 0)
				->groupBy('ca.Date')
				->having(DB::raw('SUM(Amount)'), '>', 0)->get();
		
		$months = ((date('Y', $dateTo) - date('Y', $dateFrom)) * 12) + (date('n', $dateTo) - date('n', $dateFrom));
        $totalAmount = 0;
		if (count($categories)) {
			foreach ($categories as $category) {
				if (! $category->Amount) {
					$category->Amount = 0;
				}
                $totalAmount = $totalAmount + $category->Amount;
			}
		}
			
		
		return array(
			'Months' => $months + 1,
			'Categories' => $categories,
            'TotalAmount' => $totalAmount
		);
	}
	
	/**
     * Copied and edited from the function taxonomy_get_tree() of Drupal
     *
     * @param $categoryList
	 * @param $addPrefix
     */
	public static function buildTree($categoryList, $addPrefix = false)
	{
		$parent = 0;
		$children = array();
        $parents = array();
        $categories = array();
        foreach ($categoryList as $category) {
            $children[$category->Parent][] = $category->CategoryID;
            $parents[$category->CategoryID][] = $category->Parent;
            $categories[$category->CategoryID] = $category;
        }
        
        // Initialize the tree that will be returned
        $tree = array();
        // Keeps track of the parents we have to process, the last entry is used for the next processing step
        $processParents = array($parent);
        
        // Loops over the parent terms and adds its children to the tree array.
        // Uses a loop instead of a recursion, because it's more efficient (Drupal said).
        while (count($processParents)) {
            $parent = array_pop($processParents);
            // The number of parents determines the current depth
            $depth = count($processParents);
            if (! empty($children[$parent])) {
                $hasChildren = false;
                $child = current($children[$parent]);
                do {
                    if (empty($child)) {
                        break;
                    }
                    $category = $categories[$child];
                    if (isset($parents[$category->CategoryID])) {
                        // Clone the category so that the depth attribute remains
                        // correct in the event of multiple parents
                        $category = clone $category;
                    }
                    $category->Depth = $depth;
					if ($addPrefix) {
						$category->Name = str_repeat('-', $category->Depth * 3) . ' ' . $category->Name;
					}
                    $tree[] = $category;
                    
                    if (! empty($children[$category->CategoryID])) {
                        $hasChildren = TRUE;
                        // We have to continue with this parent later.
                        $processParents[] = $parent;
                        // Use the current term as parent for the next iteration.
                        $processParents[] = $category->CategoryID;
                        // Reset pointers for child lists because we step in there more often
                        // with multi parents.
                        reset($children[$category->CategoryID]);
                        // Move pointer so that we get the correct term the next time.
                        next($children[$parent]);
                        break;
                    }
                } while ($child = next($children[$parent]));
                
                if (! $hasChildren) {
                    // We processed all categories in this hierarchy-level, reset pointer
                    // so that this function works the next time it gets called
                    reset($children[$parent]);
                }
            }
        }
        
        return $tree;
	}
	
	/**
	 * Get all parents of a category and include the category itself
	 */
	public static function getAllParents($categoryID)
	{
		$parents = array($categoryID);
		$count = 0;
		while ($parent = self::getParent($parents[$count])) {
			$parents[] = $parent;
			$count++;
		}
		
		return $parents;
	}
	
	public static function getParentsString($categoryID, $categoryName)
	{
		$parents = array($categoryID);
		$count = 0;
		while ($parent = self::getParent($parents[$count])) {
			$categoryName = self::getCategoryName($parent) . ' - ' . $categoryName;
			$parents[] = $parent;
			$count++;
		}
		
		return $categoryName;
	}
	
	public static function getCategoryName($categoryID)
	{
		return DB::table('Category')
				->select('Name')
				->where('CategoryID', $categoryID)
				->pluck('Name');
	}
	
	public static function getParent($categoryID)
	{
		return DB::table('Category')
				->select('Parent')
				->where('CategoryID', $categoryID)
				->pluck('Parent');
	}
	
	public static function getApp($categoryID)
	{
		return DB::table('Category')
				->select('App')
				->where('CategoryID', $categoryID)
				->pluck('App');
	}
	
	public static function getAppAbrr($machineName)
	{
		$appList = Config::get('app.appList');
		if (isset($appList[$machineName])) {
			return $appList[$machineName]['abbr'];
		}
		
		return '';
	}
	
	public static function getAppName($machineName)
	{
		$appList = Config::get('app.appList');
		if (isset($appList[$machineName])) {
			return $appList[$machineName]['name'];
		}
		
		return '';
	}
    
    public static function onPreQuery(\Illuminate\Database\Query\Builder $query, &$where = null)
    {
        if ($where != null) {
            if (isset($where['UserID'])) {
                unset($where['UserID']);
            }
        }
    }
    
    public static function getAll(array $where = array(), array $sort = array(), $limit = 10, $offset = 0) 
    {
        $categories = parent::getAll($where, $sort, $limit, $offset);

        $categoryReturnList = array();
		
		if (count($categories)) {
			$currentApp = '';
			
			foreach ($categories as $category) {
					$currentApp = $category->App;
                    unset($category->App);
                    
					$category->App = new stdClass();
					$category->App->MachineName = $currentApp;
					$category->App->AbbrName = self::getAppAbrr($currentApp);
					$category->App->Name = self::getAppName($currentApp);		
			}
		}
            
		return $categories;
    }
}