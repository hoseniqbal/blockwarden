<?php
/**
 * Disposable email domains.
 *
 * @package BlockWarden
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A bundled list of throwaway inbox services, plus the owner's own additions.
 */
class BlockWarden_Disposable {

	/**
	 * Bundled list.
	 *
	 * @return string[]
	 */
	public static function bundled() {
		return array(
			'10minemail.com', '10minutemail.com', '10minutemail.net', '20minutemail.com', '33mail.com', 'anonbox.net',
			'anonymbox.com', 'binkmail.com', 'bobmail.info', 'bugmenot.com', 'burnermail.io', 'byom.de', 'chacuo.net',
			'cool.fr.nf', 'correotemporal.org', 'courriel.fr.nf', 'crazymailing.com', 'deadaddress.com', 'despam.it',
			'devnullmail.com', 'discard.email', 'discardmail.com', 'discardmail.de', 'disposableemailaddresses.com',
			'dispostable.com', 'dodgit.com', 'dropmail.me', 'e4ward.com', 'emailondeck.com', 'emailtemporanea.net',
			'emailtemporario.com.br', 'emailwarden.com', 'emltmp.com', 'emz.net', 'fakeinbox.com', 'fakemail.net',
			'fakemailgenerator.com', 'fastacura.com', 'filzmail.com', 'getairmail.com', 'getnada.com',
			'gishpuppy.com', 'guerrillamail.biz', 'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.info',
			'guerrillamail.net', 'guerrillamail.org', 'guerrillamailblock.com', 'harakirimail.com', 'hidemail.de',
			'inboxbear.com', 'inboxkitten.com', 'incognitomail.org', 'jetable.fr.nf', 'jetable.org', 'kasmail.com',
			'killmail.net', 'klzlk.com', 'koszmail.pl', 'kurzepost.de', 'laste.ml', 'lroid.com', 'mail-temp.com',
			'mail.tm', 'mail7.io', 'mailcatch.com', 'maildrop.cc', 'mailexpire.com', 'mailforspam.com',
			'mailinator.com', 'mailinator.net', 'mailinator2.com', 'mailmoat.com', 'mailnesia.com', 'mailnull.com',
			'mailpoof.com', 'mailsac.com', 'mailtemp.info', 'mintemail.com', 'moakt.com', 'mohmal.com', 'mt2015.com',
			'mytemp.email', 'mytrashmail.com', 'nada.email', 'no-spam.ws', 'nomail.xl.cx', 'nospamfor.us',
			'nowmymail.com', 'objectmail.com', 'onewaymail.com', 'owlymail.com', 'pookmail.com', 'proxymail.eu',
			'rcpt.at', 'rmqkr.net', 'sharklasers.com', 'shitmail.me', 'shortmail.net', 'sneakemail.com',
			'sofimail.com', 'spam4.me', 'spambog.com', 'spambox.us', 'spamex.com', 'spamfree24.org',
			'spamgourmet.com', 'spamhole.com', 'spaml.de', 'spammotel.com', 'spamspot.com', 'spamthis.co.uk',
			'superrito.com', 'tafmail.com', 'teleworm.us', 'temp-mail.io', 'temp-mail.org', 'temp-mail.ru',
			'tempail.com', 'tempemail.net', 'tempinbox.com', 'tempmail.dev', 'tempmail.net', 'tempmail.plus',
			'tempmailo.com', 'tempomail.fr', 'temporaryemail.net', 'temporaryinbox.com', 'tempr.email',
			'thankyou2010.com', 'throwam.com', 'throwawaymail.com', 'tmail.ws', 'tmailinator.com', 'tmpmail.net',
			'tmpmail.org', 'trash-mail.com', 'trash2009.com', 'trashmail.at', 'trashmail.com', 'trashmail.de',
			'trashmail.me', 'trashmail.net', 'trashymail.com', 'trbvm.com', 'tyldd.com', 'uroid.com',
			'wegwerfemail.de', 'wegwerfmail.de', 'wegwerfmail.net', 'wh4f.org', 'yepmail.net', 'yopmail.com',
			'yopmail.fr', 'yopmail.net', 'zetmail.com', 'zoemail.org',
		);
	}

	/**
	 * Is this address on a disposable domain?
	 *
	 * @param string $email Address.
	 * @return bool
	 */
	public static function is_disposable( $email ) {
		$at = strrpos( (string) $email, '@' );
		if ( false === $at ) {
			return false;
		}
		$domain = strtolower( trim( substr( $email, $at + 1 ) ) );
		if ( in_array( $domain, BlockWarden_Settings::domains( 'allowed_domains' ), true ) ) {
			return false;
		}
		$list = array_merge( self::bundled(), BlockWarden_Settings::domains( 'blocked_domains' ) );
		$list = (array) apply_filters( 'blockwarden_disposable_domains', $list );
		foreach ( $list as $bad ) {
			if ( $domain === $bad || str_ends_with( $domain, '.' . $bad ) ) {
				return true;
			}
		}
		return false;
	}
}
