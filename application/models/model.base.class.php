<?php
	/**
	 * model 的基类
	 *
	 *  Copyright(c) 2011-2012 by surgesoft. All rights reserved
	 *
	 * To contact the author write to {@link mailto:surgesoft@gmail.com}
	 *
	 * @author surgesoft
	 * @version $Id: model.base.class.php 2012-01-06 16:06
	 * @package model.base.class.php
	 
	 * 关于数据库封装的效率
	 * 在渣电脑上不使用缓存直接查询，
	 * 测试10,000条1对多的多表连接数据查询的耗时在8.2606220245361～8.4245231151581秒之间
	 * 基本上是按照ACT AS TREE的算法来做
	 * 期待更好的优化
	 
	 */
	require_once( APPLICATION_PATH."/db.conn.php");
	

	class ModelBase
	{
		public $dao;
		protected $IsDbObj;
		protected $DataStruct;
		protected $verify;
		private $instance;
		protected $obj=array();
		
		//NFA
		private $Status;									//状态标记
		private $QueryType;							//查询类型
		private $QueryColum; 						//查询名称
		private $QueryTable;  						//查询表名
		private $QueryConstraint; 					//查询约束
		private $QueryConstraintOperators;  //约束运算符
		private $QueryConstraintValue;			//约束值
		private $StatusArr = array("Get"=>"select","Set"=>"update","New"=>"insert","Del"=>"DELETE");
		//表间关系
		protected $one_to_one;
		protected $one_to_many = array();
		protected $many_to_many = array();
		protected $join_sql;
		
		public function  __construct($instance)
		{
			$this->dao=& new database();
			$this->instance = $instance;
			//	var_dump($this->DataStruct);
		}
		public function getresult()
		{
			//if(empty($this->obj)) echo "空";
			return $this->obj;
		}
		
		/**
		*
		*
		*
		**/
		public function __call($FuncName,$arg)
		{
			
				if($this->IsDbObj)
				{
					
					$instruct =  explode('_',$FuncName);
					//echo $FuncName;
					//这部分应该是在模拟一个确定性有穷自动机进行SQL的自动生成
					//但是不知道实现是否标准。。。
					//反正就是这个意思。。
				$this->Status=0;
				$this->QueryType="";
				$this->QueryColum="";
				$this->QueryConstraint="";
				foreach ($instruct as $value)
				{
					//echo $value;
					if(!$this->state_next($value) )
					{
						//echo "语法错误";
						break;
					}
						//else echo "成功接收|";

				}
					//var_dump($instruct);
					if($this->Status==99)//接收成功
					{
						//echo "全部接收成功>>>";
						switch($this->QueryType){
							case "select":
								$sql = $this->QueryType." {$this->QueryColum} from `{$this->instance }`  {$this->join_sql} where `{$this->instance }`.`{$this->QueryConstraint}` ='".$arg[0]."'";
								break;
							case "update":
								if(!empty($this->verify)) $check=" and `".$this->verify."` ='".$_SESSION['USERID']."'";
								$sql = $this->QueryType." `".$this->instance."` set ".$this->QueryColum." = '".$arg[1]."' where `".$this->QueryConstraint."` = '".$arg[0]."'".$check;
								break;
							case "insert":
								$list = implode(",",$this->DataStruct);
								$argvalue="'".implode("','",$arg[0])."'";
								$sql = "insert into `".$this->instance."`(".$list.") VALUES(".$argvalue.")";
								break;
							case "DELETE":
								$sql = $this->QueryType." from `{$this->instance }` where  `{$this->instance }`.`{$this->QueryConstraint}` ='".$arg[0]."'";
								echo $sql ;
								break;
							}
						$this->dao->fetch($sql);
						
						/* 数据库对象的抽象 
						*  其实也就是只是一次去冗余的封装而已。。
						*  把join出来的表根据重复项和从属关系
						*  分散到数组的各个键值
						*/
						while($list = $this->dao->getRow () )
						{
							//var_dump($list);
							$echoid =$arg[0];			
							//echo "!!".$list["UserId"]."!!";
							if(!isset($this->obj[$echoid]))
							{
								$this->obj[$echoid] = array();
							}
							array_push($this->obj[$echoid],$list);
							foreach($this->one_to_many as $other)
							{
								$mark=ucwords($other[0])."Id";
								if(!isset($this->obj[$echoid][$other[0]])) $this->obj[$echoid][$other[0]] =array();
								
								$temp_arr=array($list[$mark] => $list);
								//var_dump($temp_arr);
								$this->obj[$echoid][$other[0]][$list[$mark]]=$list;
							}
						}
						
					}
					else
					{
					//echo "语法错误：".$FuncName;
					}
				}
				else
				{
					echo __CLASS__.":访问错误的函数";
				}
				
		}
		
		/**
		*
		*
		*
		**/
		private function state_next($letter)
		{
			if(empty($this->QueryType) ) //初始状态
			{
				if(isset($this->StatusArr[$letter]))
				{
					$this->QueryType= $this->StatusArr[$letter];
					$this->Status = 1;
					if($this->QueryType == "insert") 	$this->Status = 99;
					return true;
				}
				else return false;
			}
			else
			{
				switch($this->QueryType)
				{
					case "select":
					case "update":
						switch($this->Status)
						{
							case 1:
								if($letter=="By") 
								{
									if(empty($this->QueryColum))
										$this->QueryColum ="*";
									$this->Status=3;
								}
								else
								{
									if($letter=="ALL") $this->QueryColum ="*";
									else 
									{
										if(!empty($this->QueryColum)) $this->QueryColum .=", `".$this->instance."`.`".$letter."`";
										else $this->QueryColum =" `".$this->instance."`.`".$letter."`";
									}
									$this->Status=1;
								}
								return true;
								break;
							case 2:
								if($letter=="By") $this->Status=3;
								else return false;
								return true;
								break;
							case 3:
								$this->QueryConstraint = $letter;
								$this->Status=99;
								return true;
								break;
								
						}
						break;
						case "DELETE":
							switch($this->Status)
							{
								case 1:
									if($letter=="By") $this->Status=2;
									else return false;
									return true;
									break;
								case 2:
									$this->QueryConstraint = $letter;
									$this->Status=99;
									return true;
									break;
							}
							break;
						
						
						
				}
			}
		}
		/*
		*表间关系处理
		*分别有：
		*	 一对一关系
		*	一对多关系
		*   （多对多关系一般会借助一张连接表来实现，解决方法参照RoR，尝试查找一个名为$othermodel_$instance 的表）
		*	
		*   表间关系的定义需要双向定义。
		*   例如table1 与table2是一对一关系，
		*   则需要在table1中定义 has_one("table2")
		*   并同时在table2中定义 belongs_to("table1")
		*   才可以正常进行查询操作。
		*   若是只定义table1的has_one而没用定义table2的 belongs_to
		*   将只能在table1中查询到table2，
		*   而不能从table2中查询到table1.
		*   当然你可以纯手动- -
		*
		*/
		protected function has_many($othermodel,$foreignkey)
		{
			//if(empty($this->one_to_many)) echo "empty!!";
			$arr=array($othermodel,$foreignkey);
			array_push($this->one_to_many,$arr);
			//var_dump($this->one_to_many);
			$this->join_sql.="LEFT JOIN  `{$othermodel}` ON  `{$this->instance}`.`{$this->instance}ID` =  `{$othermodel}`.`{$foreignkey}` ";
			//echo 	$this->join_sql;
		}
		protected function belongs_to($othermodel,$foreignkey)
		{
				$arr=array($othermodel,$foreignkey);
				array_push($this->one_to_many,$arr);
				$this->join_sql.="LEFT JOIN  `{$othermodel}` ON  `{$this->instance}`.`{$this->instance}ID` =  `{$othermodel}`.`{$foreignkey}` ";
		}
	}

?>