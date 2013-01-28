<?php
namespace Device;

class Device
{

	const DOCOMO = 'docomo';
	const AU = 'au';
	const SOFTBANK = 'softbank';
	const WILLCOM = 'willcom';
	const ANDROID = 'android';
	const IOS = 'ios';
	const OTHER = 'other';

	private static $carrier = null;
	private static $uid = null;
	private static $model = null;

	public static $precision = null;

	public static function _init()
	{
		\Config::load('device', true);
		static::$precision = \Config::get('device.precision');
	}

	public static function get($precision = null, $user_agent = null)
	{
		if ( ! is_null(static::$carrier)) return static::$carrier;
		
		if (static::is_docomo($precision, $user_agent))
		{
			static::$carrier = static::DOCOMO;
		}
		elseif (static::is_au($precision, $user_agent))
		{
			static::$carrier = static::AU;
		}
		elseif (static::is_softbank($precision, $user_agent))
		{
			static::$carrier = static::SOFTBANK;
		}
		elseif (static::is_willcom($precision, $user_agent))
		{
			static::$carrier = static::WILLCOM;
		}
		elseif (static::is_android($precision, $user_agent))
		{
			static::$carrier = static::ANDROID;
		}
		elseif (static::is_ios($precision, $user_agent))
		{
			static::$carrier = static::IOS;
		}
		else
		{
			static::$carrier = static::OTHER;
		}

		return static::$carrier;
	}

	public static function uid($precision = null, $user_agent = null)
	{
		if ( ! is_null(static::$uid)) return static::$uid;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (is_null(static::$carrier)) static::get($precision, $user_agent);

		switch (static::$carrier)
		{
			case static::DOCOMO:
				return @$_SERVER['HTTP_X_DCMGUID'];
			case static::AU:
				return @$_SERVER['HTTP_X_UP_SUBNO'];
			case static::SOFTBANK:
				return @$_SERVER['HTTP_X_JPHONE_UID'];
		}
		return null;
	}

	public static function model($precision = null, $user_agent = null)
	{
		if ( ! is_null(static::$model)) return static::$model;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (is_null(static::$carrier)) static::get($precision, $user_agent);

		static::$model = '';
		switch (static::$carrier)
		{
			case static::DOCOMO:
				list($main, $foma_or_comment) = explode(' ', $user_agent, 2);
				if ( ! $foma_or_comment or
					($foma_or_comment and preg_match('/^\((.*)\)$/', $foma_or_comment, $matches)))
				{
					// DoCoMo/1.0/R692i/c10
					// DoCoMo/1.0/P209is (Google CHTML Proxy/1.0)
					list($name, $version, static::$model, $cache, $rest) = explode('/', $main, 5);
				}
				elseif ($foma_or_comment)
				{
					// DoCoMo/2.0 N2001(c10;ser0123456789abcde;icc01234567890123456789)
					if (preg_match('/^([^(]+)/', $foma_or_comment, $matches)) static::$model = $matches[1];
				}
				//myuをμに変換
				static::$model = str_replace('myu', 'μ', static::$model);
				//2をⅡに変換
				if (substr(static::$model, -1) === '2')
				{
					static::$model = substr(static::$model, 0, -1).'Ⅱ';
				}
				break;
			case static::AU:
				$agent = $user_agent;
				if (preg_match('/^KDDI-(.*)/', $agent, $matches))
				{
					// KDDI-TS21 UP.Browser/6.0.2.276 (GUI) MMP/1.1
					list(static::$model) = explode(' ', $matches[1]);
					$model_array = static::models(static::AU);
					if ($model_array[static::$model]) static::$model = $model_array[static::$model];
				}
				else
				{
					// UP.Browser/3.01-HI01 UP.Link/3.4.5.2
					list($browser) = explode(' ', $agent);
					list($name, $software) = explode('/', $browser);
					list($version, static::$model) = explode('-', $software);
				}
				break;
			case static::SOFTBANK:
				$agent = explode(' ', $user_agent);
				preg_match('!^(?:(SoftBank|Vodafone|J-PHONE)/\d\.\d|MOT-)!', $agent[0], $matches);
				if (count($matches) > 1)
				{
					$carrier = $matches[1];
				}
				else
				{
					$carrier = 'Motorola';
				}
				switch ($carrier){
					case 'Vodafone':
					case 'SoftBank':
						list($name, $version, static::$model) = explode('/', $agent[0]);
						break;
					case 'J-PHONE':
						// J-PHONE/4.0/J-SH51/SNJSHA3029293 SH/0001aa Profile/MIDP-1.0 Configuration/CLDC-1.0 Ext-Profile/JSCL-1.1.0
						// J-PHONE/2.0/J-DN02
						list($name, $version, static::$model) = explode('/', $agent[0]);
						break;
					case 'Motorola':
						// MOT-V980/80.2F.2E. MIB/2.2.1 Profile/MIDP-2.0 Configuration/CLDC-1.1
						list(static::$model) = explode('/', $agent[0]);
						break;
				}
				break;
		}

		return static::$model;
	}

	public static function models($carrier)
	{
		$model_array = null;
		switch ($carrier)
		{
			case static::AU:
				$model_array = include(__DIR__.DS.'..'.DS.'models'.DS.$carrier.'.php');
				break;
		}
		return $model_array;
	}

	public static function url($url, $precision = null, $user_agent = null)
	{
		if (is_null(static::$carrier)) static::get($precision, $user_agent);
		if (static::$carrier != static::DOCOMO) return $url;
		if (strpos($url, '?') === false)
		{
			$url .= '?';
		}
		else
		{
			$url .= '&';
		}
		return $url .= 'guid=on';
	}

	public static function is_feature_phone($precision = null, $user_agent = null)
	{
		if (is_null(static::$carrier)) static::get($precision, $user_agent);
		switch (static::$carrier)
		{
			case static::DOCOMO:
			case static::AU:
			case static::SOFTBANK:
			case static::WILLCOM:
				return true;
		}
		return false;
	}

	public static function is_smart_phone($precision = null, $user_agent = null)
	{
		if (is_null(static::$carrier)) static::get($precision, $user_agent);
		switch (static::$carrier)
		{
			case static::ANDROID:
			case static::IOS:
				return true;
		}
		return false;
	}

	public static function is_docomo($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (preg_match('!^DoCoMo!', $user_agent) == 0)
		{
			return false;
		}
		if ($precision < 1) return true;
		if ( ! static::check_zone('docomo')) return false;
		return true;
	}

	public static function is_au($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (preg_match('!^KDDI-!', $user_agent) == 0 and
			preg_match('!^UP\.Browser!', $user_agent) == 0)
		{
			return false;
		}
		if ($precision < 1) return true;
		if ( ! static::check_zone('au')) return false;
		return true;
	}

	public static function is_softbank($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (preg_match('!^SoftBank!', $user_agent) == 0 and
			preg_match('!^Semulator!', $user_agent) == 0 and
			preg_match('!^Vodafone!', $user_agent) == 0 and
			preg_match('!^Vemulator!', $user_agent) == 0 and
			preg_match('!^MOT-!', $user_agent) == 0 and
			preg_match('!^MOTEMULATOR!', $user_agent) == 0 and
			preg_match('!^J-PHONE!', $user_agent) == 0 and
			preg_match('!^J-EMULATOR!', $user_agent) == 0)
		{
			return false;
		}
		if ($precision < 1) return true;
		if ( ! static::check_zone('softbank')) return false;
		return true;
	}

	public static function is_willcom($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (preg_match('!^Mozilla/3\.0\((?:DDIPOCKET|WILLCOM|PDA);!', $user_agent) == 0)
		{
			return false;
		}
		if ($precision < 1) return true;
		if ( ! static::check_zone('willcom')) return false;
		return true;
	}

	public static function is_ios($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'iPhone') === false and
			strpos($user_agent, 'iPod') === false and
			strpos($user_agent, 'iPad') === false)
		{
			return false;
		}
		return true;
	}

	public static function is_android($precision = null, $user_agent = null)
	{
		is_null($precision) and $precision = static::$precision;
		is_null($user_agent) and $user_agent = @$_SERVER['HTTP_USER_AGENT'];
		if (strpos($user_agent, 'Android') === false and
			strpos($user_agent, 'dream') === false and
			strpos($user_agent, 'CUPCAKE') === false and
			strpos($user_agent, 'blackberry9500') === false and
			strpos($user_agent, 'blackberry9530') === false and
			strpos($user_agent, 'blackberry9520') === false and
			strpos($user_agent, 'blackberry9550') === false and
			strpos($user_agent, 'blackberry9800') === false and
			strpos($user_agent, 'webOS') === false and
			strpos($user_agent, 'incognito') === false and
			strpos($user_agent, 'webmate') === false)
		{
			return false;
		}
		return true;
	}

	public static function zones($carrier)
	{
		return explode("\n", file_get_contents(__DIR__.DS.'..'.DS.'zones'.DS.$carrier.'.txt'));
	}

	private static function check_zone($carrier = null)
	{
		empty($ip) and $ip = @$_SERVER['REMOTE_ADDR'];
		if (empty($ip)) return false;
		$zone_array = static::zones($carrier);
		foreach ($zone_array as $zone)
		{
			if (static::check_cidr($ip, $zone)) return true;
		}
		return false;
	}

	private static function check_cidr($ip, $cidr)
	{
		list($net, $mask) = explode('/', $cidr) + array(null, null);
		if (empty($net) or is_null($mask)) return false;
		$ip_net = ip2long($net);
		$ip_mask = ~((1 << (32 - $mask)) - 1);
		$ip_ip = ip2long($ip);
		$ip_ip_net = $ip_ip & $ip_mask;
		return ($ip_ip_net == $ip_net);
	}

}