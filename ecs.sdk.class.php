<?php
/*
  The php sdk class for alibaba cloud ecs api.
  Author: enj0y
  Email: hackes@outlook.com
  Project page: https://github.com/thislancorp/AliECS_PHP_SDK/
  Latest ECS Api reference: http://oss.aliyuncs.com/developers/API/ECS-API-Reference.pdf
 */
Class ECS{
	protected static $accessKeyID=null,$accessKeySec=null,$accessGetway="http://ecs.aliyuncs.com",$data=null,$version='2013-01-10';
	
	protected static function xml2array($contents, $get_attributes=1, $priority = 'tag') {
		// Parse XML Body to php array
		if(!$contents) return array(); 

		if(!function_exists('xml_parser_create')) {
			//print "'xml_parser_create()' function not found!";
			return array();
		} 

		//Get the XML parser of PHP - PHP must have this module for the parser to work
		$parser = xml_parser_create('');
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, trim($contents), $xml_values);
		xml_parser_free($parser); 

		if(!$xml_values) return;//Hmm... 

		//Initializations
		$xml_array = array();
		$parents = array();
		$opened_tags = array();
		$arr = array(); 

		$current = &$xml_array; //Refference 

		//Go through the tags.
		$repeated_tag_index = array();//Multiple tags with same name will be turned into an array
		foreach($xml_values as $data) {
			unset($attributes,$value);//Remove existing values, or there will be trouble 

			//This command will extract these variables into the foreach scope
			// tag(string), type(string), level(int), attributes(array).
			extract($data);//We could use the array by itself, but this cooler. 

			$result = array();
			$attributes_data = array(); 

			if(isset($value)) {
				if($priority == 'tag') $result = $value;
				else $result['value'] = $value; //Put the value in a assoc array if we are in the 'Attribute' mode
			} 

			//Set the attributes too.
			if(isset($attributes) and $get_attributes) {
				foreach($attributes as $attr => $val) {
					if($priority == 'tag') $attributes_data[$attr] = $val;
					else $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
				}
			} 

			//See tag status and do the needed.
			if($type == "open") {//The starting of the tag '<tag>'
				$parent[$level-1] = &$current;
				if(!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
					$current[$tag] = $result;
					if($attributes_data) $current[$tag. '_attr'] = $attributes_data;
					$repeated_tag_index[$tag.'_'.$level] = 1; 

					$current = &$current[$tag]; 

				} else { //There was another element with the same tag name 

					if(isset($current[$tag][0])) {//If there is a 0th element it is already an array
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result;
						$repeated_tag_index[$tag.'_'.$level]++;
					} else {//This section will make the value an array if multiple tags with the same name appear together
						$current[$tag] = array($current[$tag],$result);//This will combine the existing item and the new item together to make an array
						$repeated_tag_index[$tag.'_'.$level] = 2; 

						if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well
							$current[$tag]['0_attr'] = $current[$tag.'_attr'];
							unset($current[$tag.'_attr']);
						} 

					}
					$last_item_index = $repeated_tag_index[$tag.'_'.$level]-1;
					$current = &$current[$tag][$last_item_index];
				} 

			} elseif($type == "complete") { //Tags that ends in 1 line '<tag />'
				//See if the key is already taken.
				if(!isset($current[$tag])) { //New Key
					$current[$tag] = $result;
					$repeated_tag_index[$tag.'_'.$level] = 1;
					if($priority == 'tag' and $attributes_data) $current[$tag. '_attr'] = $attributes_data; 

				} else { //If taken, put all things inside a list(array)
					if(isset($current[$tag][0]) and is_array($current[$tag])) {//If it is already an array... 

						// ...push the new element into that array.
						$current[$tag][$repeated_tag_index[$tag.'_'.$level]] = $result; 

						if($priority == 'tag' and $get_attributes and $attributes_data) {
							$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
						}
						$repeated_tag_index[$tag.'_'.$level]++; 

					} else { //If it is not an array...
						$current[$tag] = array($current[$tag],$result); //...Make it an array using using the existing value and the new value
						$repeated_tag_index[$tag.'_'.$level] = 1;
						if($priority == 'tag' and $get_attributes) {
							if(isset($current[$tag.'_attr'])) { //The attribute of the last(0th) tag must be moved as well 

								$current[$tag]['0_attr'] = $current[$tag.'_attr'];
								unset($current[$tag.'_attr']);
							} 

							if($attributes_data) {
								$current[$tag][$repeated_tag_index[$tag.'_'.$level] . '_attr'] = $attributes_data;
							}
						}
						$repeated_tag_index[$tag.'_'.$level]++; //0 and 1 index is already taken
					}
				} 

			} elseif($type == 'close') { //End of tag '</tag>'
				$current = &$parent[$level-1];
			}
		} 

		return($xml_array);
	}

	protected static function percentEncode($str){
		$res = urlencode($str);
		$res = preg_replace('/\+/', '%20', $res);
		$res = preg_replace('/\*/', '%2A', $res);
		$res = preg_replace('/%7E/', '~', $res);
		return $res;
	}
	
	protected static function sign($parameters, $accessKeySecret){
		// 将参数Key按字典顺序排序
		ksort($parameters);

		// 生成规范化请求字符串
		$canonicalizedQueryString = '';
		foreach($parameters as $key => $value){
			$canonicalizedQueryString .= '&' . self::percentEncode($key). '=' . self::percentEncode($value);
		}

		// 生成用于计算签名的字符串 stringToSign
		$stringToSign = 'GET&%2F&' .self::percentencode(substr($canonicalizedQueryString, 1));

		// 计算签名，注意accessKeySecret后面要加上字符'&'
		$signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
		return $signature;
	}

	protected static function curl($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$res = curl_exec($ch);
		curl_close($ch);
		return $res;
	}

	protected static function nonce(){
		return uniqid();
	}
	
	protected static function gmdateTZ(){
		return 'Y-m-d\TH:i:s\Z';
	}
	
	protected static function httpParams($data){
		return http_build_query($data);
	}
	
	protected static function auth($params=array(),$curl=true){
		// $params 请求数据，$curl 是否需要请求出去,默认true,为false时会返回签名的URL
		self::$data = array(
			// 公共参数
			'Format' => 'XML',
			'Version' => self::$version,
			'AccessKeyId' => self::$accessKeyID,
			'SignatureVersion' => '1.0',
			'SignatureMethod' => 'HMAC-SHA1',
			'SignatureNonce'=> self::nonce(),
			'TimeStamp' => date(self::gmdateTZ()), 
		);
		foreach($params as $k => $v ){
			self::$data[$k]=$v;
		}
		self::$data['Signature'] = self::sign(self::$data, self::$accessKeySec);
		$url= self::$accessGetway .'/?' . self::httpParams(self::$data);
		if($curl===true){
			return self::xml2array(self::curl($url));
		}else{
			return $url;
		}
	}
	
	function __construct($keyid="",$keysec="",$getway="http://ecs.aliyuncs.com"){
		if($keyid!=="")self::$accessKeyID=$keyid;
		if($keysec!=="")self::$accessKeySec=$keysec;
		self::$accessGetway=$getway;
		if(self::$data===null)self::$data=array();
		date_default_timezone_set("GMT");
	}
	
	/**
	 Start/PowerON the Given ECS Instance
	 */
	public function startInstance($instanceId){
		$data=array();
		$data['Action']="StartInstance";
		$data['InstanceId']=$instanceId;
		return self::auth($data);
	}

	/**
	 Stop/PowerOFF the Given ECS Instance
	 ForceStop means the electric break stopping.
	 */
	public function stopInstance($instanceId,$forceStop=false){
		$data=array();
		$data['InstanceId']=$instanceId;
		$data['Action']="StopInstance";
		$data['ForceStop']=$forceStop?"true":"false";
		return self::auth($data);
	}

	/**
	 Reboot the Given ECS Instance
	 ForceStop means the electric break restarting.
	 */
	public function rebootInstance($instanceId,$forceStop=false){
		$data=array();
		$data['Action']="RebootInstance";
		$data['InstanceId']=$instanceId;
		$data['ForceStop']=$forceStop?"true":"false";
		return self::auth($data);
	}

	/**
	 Reset the Given ECS Instance
	 ImageId is the VHD code.
	 DiskType is "system"/"data" disk.
	 */
	public function resetInstance($instanceId,$imageId="",$diskType="system"){
		$data=array();
		$data['Action']="ResetInstance";
		$data['InstanceId']=$instanceId;
		if($imageId!=="")$data['ImageId']=$imageId;
		$data['DiskType']=$diskType;
		return self::auth($data);
	}

	/**
	 Modify the Given ECS Instance
		Password is the password wanna set to be.
		HostName is the new hostname.
		securityGroupId is the new Sec Group
	 */
	public function modifyInstanceAttribute($instanceId,$password="",$hostName="",$securityGroupId=""){
		$data=array();
		$data['Action']="ModifyInstanceAttribute";
		$data['InstanceId']=$instanceId;
		if($password!=="")$data['Password']=$password;
		if($hostName!=="")$data['HostName']=$hostName;
		if($securityGroupId!=="")$data['SecurityGroupId']=$securityGroupId;
		return self::auth($data);
	}

	/**
	 List ECS Instances of the given Zone.
	 ImageId is the VHD code.
	 DiskType is "system"/"data" disk.
	 */
	public function describeInstanceStatus($regionId,$zoneId,$pageNumber=1,$pageSize=10){
		$data=array();
		$data['Action']="DescribeInstanceStatus";
		$data['RegionId']=$regionId;
		$data['ZoneId']=$zoneId;
		if($pageNumber!==1)$data['PageNumber']=$pageNumber;
		if($pageSize!==10)$data['PageSize']=$pageSize;
		return self::auth($data);
	}

	/**
	 Describe the Given ECS Instance Attribute
	 */
	public function describeInstanceAttribute($instanceId){
		$data=array();
		$data['Action']="DescribeInstanceAttribute";
		$data['InstanceId']=$instanceId;
		return self::auth($data);
	}

	/**
	 List disk(s) of the Given ECS Instance Attribute
	 */
	public function describeInstanceDisks($instanceId){
		$data=array();
		$data['Action']="DescribeInstanceDisks";
		$data['InstanceId']=$instanceId;
		return self::auth($data);
	}

	/**
	 List image(s) of the Given Region
	 */
	public function describeImages($regionId,$pageNumber=1,$pageSize=10){
		$data=array();
		$data['Action']="DescribeImages";
		$data['RegionId']=$regionId;
		if($pageNumber!==1)$data['PageNumber']=$pageNumber;
		if($pageSize!==10)$data['PageSize']=$pageSize;
		return self::auth($data);
	}

	/**
	 Allocate a new PublicIpAddress for the Given ECS Instance Attribute
	 Note:the instance must have no public ip or it will return error
	 */
	public function allocatePublicIpAddress($instanceId){
		$data=array();
		$data['Action']="AllocatePublicIpAddress";
		$data['InstanceId']=$instanceId;
		return self::auth($data);
	}
	
	/**
	 Release the given PublicIpAddress
	 */
	public function releasePublicIpAddress($publicIpAddress){
		$data=array();
		$data['Action']="ReleasePublicIpAddress";
		$data['PublicIpAddress']=$publicIpAddress;
		return self::auth($data);
	}
	
	/**
	 Create a new SecurityGroup
	 */
	public function createSecurityGroup($regionId,$description){
		$data=array();
		$data['Action']="CreateSecurityGroup";
		$data['RegionId']=$regionId;
		$data['Description']=$description;
		return self::auth($data);
	}
	
	/**
	 Authorize a given SecurityGroup the network access permission
	 SecurityGroupId 安全组编码
	 RegionId 安全组所属 Region ID
	 IpProtocol IP 协议，取值：tcp|udp|icmp|gre|all；All表示同时支持四种协议
	 PortRange IP 协议相关的端口号范围，tcp、udp 协议的默认端口号，取值范围为 1~65535；例如“1/200”意思是端口号范围为 1~200，若输入值为：“200/1”接口调用将报错。icmp 协议时端口号范围值为-1/-1，gre 协议时端口号范围值为-1/-1，当IpProtocol 为 all时端口号范围值为-1/-1；取值范围SourceGroupId String 否 授权同一Region内可访问目标安全组的源安全组编码
	 SourceGroupId 或者SourceCidrIp 参数必须设置一项，如果两项都设置，则默认对
	 SourceCidrIp 授权。指定了该字段之后，NicType 只能选择 intranet
	 SourceCidrIp 授权可访问目标安全组的源 IP地址范围（采用 CIDR格式来指定 IP 地址范围），默认值为 0.0.0.0/0（表示不受限制），其他支持的格式如 10.159.6.18/12、10.159.6.186、或10.159.6.186-10.159.6.201（IP 区间）
	 Policy 授权策略，参数值可为：accept（接受访问）默认值为：accept
	 NicType 网络类型，取值：internet|intranet；默认值为 internet
	 */
	public function authorizeSecurityGroup($securityGroupId,$regionId,$ipProtocol,$portRange,$sourceGroupId=0,$sourceCidrIp=0,$policy="accept",$nicType="internet"){
		$data=array();
		$data['Action']="AuthorizeSecurityGroup";
		$data['SecurityGroupId']=$securityGroupId;
		$data['RegionId']=$regionId;
		$data['IpProtocol']=$ipProtocol;
		$data['PortRange']=$portRange;
		$data['SourceGroupId']=$sourceGroupId;
		$data['SourceCidrIp']=$sourceCidrIp;
		$data['Policy']=$policy;
		$data['NicType']=$nicType;
		return self::auth($data);
	}
	
	/**
	 Describe a given SecurityGroup
	 SecurityGroupId 安全组编码
	 RegionId 安全组所属 Region ID
	 NicType String 取值：internet|intranet 不指定时默认值为 internet
	 */
	public function describeSecurityGroupAttribute($securityGroupId,$regionId,$nicType="internet"){
		$data=array();
		$data['Action']="DescribeSecurityGroupAttribute";
		$data['SecurityGroupId']=$securityGroupId;
		$data['RegionId']=$regionId;
		$data['NicType']=$nicType;
		return self::auth($data);
	}
	
	
	/**
	 List all SecurityGroup
	 RegionId 安全组所属 Region ID
	 PageNumber 当前页码，起始值为 1，默认值为 1
	 PageSize 分页查询时设置的每页行数，最大值 50，默认值为 10
	 */
	public function describeSecurityGroups($regionId,$pageNumber=1,$pageSize=10){
		$data=array();
		$data['Action']="DescribeSecurityGroups";
		$data['RegionId']=$regionId;
		$data['PageNumber']=$pageNumber;
		$data['PageSize']=$pageSize;
		return self::auth($data);
	}
	
	/**
	 Revoke a given SecurityGroup
	 SecurityGroupId 安全组编码
	 RegionId 安全组所属 Region ID
	 IpProtocol IP 协议，取值：tcp|udp|icmp|gre|all；All表示同时支持四种协议
	 PortRange IP 协议相关的端口号范围，tcp、udp 协议的默认端口号，取值范围为 1~65535；例如“1/200”意思是端口号范围为 1~200，若输入值为：“200/1”接口调用将报错。icmp 协议时端口号范围值为-1/-1，gre 协议时端口号范围值为-1/-1，当IpProtocol 为 all时端口号范围值为-1/-1；取值范围SourceGroupId String 否 授权同一Region内可访问目标安全组的源安全组编码
	 SourceGroupId 或者SourceCidrIp 参数必须设置一项，如果两项都设置，则默认对
	 SourceCidrIp 授权。指定了该字段之后，NicType 只能选择 intranet
	 SourceCidrIp 授权可访问目标安全组的源 IP地址范围（采用 CIDR格式来指定 IP 地址范围），默认值为 0.0.0.0/0（表示不受限制），其他支持的格式如 10.159.6.18/12、10.159.6.186、或10.159.6.186-10.159.6.201（IP 区间）
	 Policy 授权策略，参数值可为：accept（接受访问）默认值为：accept
	 NicType 网络类型，取值：internet|intranet；默认值为 internet
	 */
	public function revokeSecurityGroup($securityGroupId,$regionId,$ipProtocol,$portRange,$sourceGroupId=0,$sourceCidrIp=0,$policy="accept",$nicType="internet"){
		$data=array();
		$data['Action']="RevokeSecurityGroup";
		$data['SecurityGroupId']=$securityGroupId;
		$data['RegionId']=$regionId;
		$data['IpProtocol']=$ipProtocol;
		$data['PortRange']=$portRange;
		$data['SourceGroupId']=$sourceGroupId;
		$data['SourceCidrIp']=$sourceCidrIp;
		$data['Policy']=$policy;
		$data['NicType']=$nicType;
		return self::auth($data);
	}
	
	/**
	 Delete a given SecurityGroup
	 */
	public function deleteSecurityGroup($securityGroupId,$regionId){
		$data=array();
		$data['Action']="DeleteSecurityGroup";
		$data['SecurityGroupId']=$securityGroupId;
		$data['RegionId']=$regionId;
		return self::auth($data);
	}

	/**
	 List All Regions
	 */
	public function describeRegions(){
		$data=array();
		$data['Action']="DescribeRegions";
		return self::auth($data);
	}

	/**
	 get Monitor Data of a given instance
	 */
	public function getMonitorData($regionId,$instanceId=null,$time=null,$pageNumber=null,$pageSize=null){
	    // The time and the instanceId can not request them all at the present time. 当前不支持查询某实例 在某时间的监控信息。
		$data=array();
		$data['Action']="GetMonitorData";
		$data['RegionId']=$regionId;
		if($instanceId!==null)$data['InstanceId']=$instanceId;
		if($time!==null)$data['Time']=$time;
		if($pageNumber!==null)$data['PageNumber']=$pageNumber;
		if($pageSize!==null)$data['PageSize']=$pageSize;
		
		return self::auth($data);
	}


	/**
	 List all ECS type
	 */
	public function describeInstanceTypes(){
		$data=array();
		$data['Action']="DescribeInstanceTypes";
		return self::auth($data);
	}


} 

?>

