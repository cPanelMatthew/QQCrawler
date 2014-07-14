<?php
/*
Homepage: http://netkiller.github.io
Author: Neo <netkiller@msn.com>
*/
if(!extension_loaded('pthreads')) die ('Please install pthreads');

include_once('Snoopy.class.php');

class CrawlerWorker extends Worker {

	protected  static $dbh;
	public function __construct() {

	}
	public function run(){
		include_once('Config.class.php');
	
		self::$dbh  = new PDO("mysql:host=$dbhost;port=3306;dbname=$dbname", $dbuser, $dbpass, array(
			/* PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'', */
			PDO::MYSQL_ATTR_COMPRESS => true,
			PDO::ATTR_PERSISTENT => true
			)
		);
	
	}
	protected function getInstance(){
        return self::$dbh;
    }

}

/* the collectable class implements machinery for Pool::collect */
class Crawler extends Stackable {
	public $depth = 5;
	private static $level = 0;
	public function __construct($qq) {
		$this->qq = $qq;
	}
	public function run() {
		$fistqq = array(array('uin' => $this->qq, 'nick' => null));
		try {
			$this->recursion($fistqq);
			//$this->insert($fistqq, $this->qq);
		}
		catch(PDOException $e) {
			$error = sprintf("%s\n", $e );
			file_put_contents('log/error.'.date('Y-m-d').'.log', $error, FILE_APPEND);
		}
		//printf("runtime: %s, %s\n", date('Y-m-d H:i:s'), $this->worker->getThreadId());
		//$lst = $this->qzone($this->qq);
		//print_r($lst);
	}
	public function insert($datas, $parent){
		$dbh  = $this->worker->getInstance();
		$sql = "insert into qq(uin, nick, parent) value(:uin, :nick, :parent)";
		$sth = $dbh->prepare($sql);
		
		foreach($datas as $data){
			//print_r($parent);
			$sth->bindValue(':uin', $data['uin']);
			$sth->bindValue(':nick',$data['nick']);
			$sth->bindValue(':parent',$parent);
			$sth->execute();		
		}
		$dbh = null;
		//echo $sth->queryString;
		//echo $sth->debugDumpParams();
	}
	public function recursion($qqs, $parent = null){
		
		if( self::$level <= $this->depth){
			self::$level++;
		}else if(self::$level > 0){
			self::$level--;
		}
		//printf("Level: %s, %s\n", self::$level, $parent);
		//print_r($qqs);
		//sleep(1);
		usleep(mt_rand(10000,1000000));
		if(self::$level >= $this->depth){
			return;
		}
		
		foreach($qqs as $qq) {
			$uin = $qq['uin'];
			$lst = $this->qzone($uin);
			if($lst){
				$this->insert($lst, $uin);
				//print_r($lst);
				$this->recursion($lst, $uin);
			}
		}
	}

	public function qzone($qq){
		$url = 'http://m.qzone.com/mqz_get_visitor?g_tk=1191852101&res_mode=0&res_uin='.$qq.'&offset=0&count=100&page=1&format=json&t=1401762986882&sid=dODKVcYv6azjN87cxXQ5mao1xgakYjHg18c8aa5e0201%3D%3D';
		$snoopy = new Snoopy;
		 
		// need an proxy?
		//$snoopy->proxy_host = "my.proxy.host";
		//$snoopy->proxy_port = "8080";
		 
		// set browser and referer:
		$snoopy->agent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)";
		$snoopy->referer = "http://m.qzone.com/";
		 
		// set some cookies:
		//$snoopy->cookies["SessionID"] = '238472834723489';
		//$snoopy->cookies["favoriteColor"] = "blue";
		 
		// set an raw-header:
		$snoopy->rawheaders["Pragma"] = "no-cache";
		 
		// set some internal variables:
		$snoopy->maxredirs = 2;
		$snoopy->offsiteok = false;
		$snoopy->expandlinks = false;
		 
		// set username and password (optional)
		//$snoopy->user = "joe";
		//$snoopy->pass = "bloe";
		 
		// fetch the text of the website www.google.com:
		if($snoopy->fetchtext($url)){ 
			// other methods: fetch, fetchform, fetchlinks, submittext and submitlinks

			// response code:
			//print "response code: ".$snoopy->response_code."<br/>\n";
		 
			// print the headers:
			//print "<b>Headers:</b><br/>";
			//while(list($key,$val) = each($snoopy->headers)){
			//	print $key.": ".$val."<br/>\n";
			//}

			// print the texts of the website:
			//print_r( json_decode($snoopy->results) );
			
			$results = array();
			$tmp = json_decode($snoopy->results);
			
			if($tmp){
				if(property_exists($tmp, 'data')){
					foreach( $tmp->data->list as $lst ){
						$results[] = array('uin'=>$lst->uin, 'nick'=>$lst->nick);
					}
				}
			}
			return ($results);
			
		}
		else {
			print "Snoopy: error while fetching document: ".$snoopy->error."\n";
		}		
	}
}

$pool = new Pool(100, \CrawlerWorker::class, []);

#foreach (range(1000, 100000) as $number) {
#	$pool->submit(new Crawler($number));
#}

$pool->submit(new Crawler('13721218'));
//$pool->submit(new Crawler('291379'));
//$pool->submit(new Crawler('xxx'));
//$pool->submit(new Crawler('xxx'));
//$pool->submit(new Crawler('xxx'));
// 以此类推
//$pool->submit(new Crawler('nnn'));

$pool->shutdown();
?>
