<?php
/**
 * Contenus de DÉPART (local / recette) : 20 fiches sur les violences numériques et 4 bandes dessinées.
 * Idempotent (mise à jour par slug) :   php core/seeders/educational_content.php
 *
 * Illustrations des bandes dessinées (themes/default/statics/ganho/img/bd/) : une BD utilise ses images WebP « premium »
 * (<slug>-<n>.webp, générées avec le connecteur Figma) dès que ses 5 cases existent ; sinon elle garde les SVG de repli générés ici. Aucun numéro d'urgence n'est inventé : les fiches renvoient vers l'alerte VBG,
 * les proches de confiance, les plateformes (signalement) et les autorités de chaque pays.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = dirname(__DIR__, 2);
chdir($root);
$_SERVER["HTTP_HOST"] = isset($_SERVER["HTTP_HOST"]) ? $_SERVER["HTTP_HOST"] : "forum.ganho.localhost";
require_once("core/web_req_init.php");

$host = (string) parse_url($site_url, PHP_URL_HOST);
$local = (bool) preg_match('/(^localhost$|\.localhost$|\.test$|^127\.0\.0\.1$)/i', $host);
if (!$local && getenv('GANHO_ALLOW_SEED') !== '1') {
	fwrite(STDERR, "Refusé : $site_url n'est pas un environnement local.\n");
	exit(1);
}

require_once(cl_full_path("core/apps/education/app_ctrl.php"));
cl_edu_install();

/* ------------------------------------------------------------------ FICHES */

/** Assemble le corps structuré d'une fiche. */
function edu_body($def, $signs, $acts, $tip, $extra = "") {
	$b  = "## De quoi parle-t-on ?\n" . $def . "\n\n";
	$b .= "## Les signes qui doivent alerter\n";
	foreach ($signs as $s) { $b .= "- " . $s . "\n"; }
	$b .= "\n## Que faire ?\n";
	foreach ($acts as $i => $a) { $b .= ($i + 1) . ". " . $a . "\n"; }
	$b .= "\n!! " . $tip . "\n\n";
	if ($extra !== "") { $b .= "## À savoir\n" . $extra . "\n\n"; }
	$b .= "## Où trouver de l’aide ?\n";
	$b .= "- **Sur GANXÔ** : créez une alerte VBG depuis le menu. L’équipe vous écoute, sans jugement, et vous oriente.\n";
	$b .= "- **Une personne de confiance** : une amie, un proche, un collègue. Parler allège déjà le poids.\n";
	$b .= "- **La plateforme concernée** : utilisez ses outils de signalement et de blocage.\n";
	$b .= "- **Les autorités de votre pays** : en cas de danger immédiat, contactez d’abord les secours.\n\n";
	$b .= "Ce qui vous arrive n’est pas de votre faute. Vous avez le droit d’être respecté·e, en ligne comme ailleurs.";
	return $b;
}

$fiches = array(
	array("Harcèlement en ligne", "harcelement", "all", true,
		"Messages répétés, insultes, intimidation ou menaces reçus sur les réseaux sociaux ou les messageries : comment le reconnaître et reprendre la main.",
		"Le harcèlement en ligne, c’est subir de façon répétée des messages, commentaires ou publications qui insultent, rabaissent, intimident ou menacent, via les réseaux sociaux, les messageries ou les jeux en ligne. Une seule attaque peut déjà blesser ; c’est la répétition et l’intention de nuire qui en font un harcèlement.",
		array("Des messages ou des appels qui arrivent à toute heure, même si vous avez demandé d’arrêter.", "Des insultes, des moqueries ou des menaces visant votre corps, votre genre, votre orientation ou votre origine.", "Des comptes qui se multiplient pour vous contacter après un blocage.", "Une peur d’ouvrir son téléphone, des troubles du sommeil, une envie de se retirer des réseaux."),
		array("**Ne répondez pas** aux provocations : cela alimente l’agresseur.", "**Gardez des preuves** : captures d’écran avec la date, l’heure et le nom du compte, liens, messages vocaux.", "**Bloquez et signalez** le compte sur la plateforme.", "**Restreignez vos comptes** : profil privé, tri des messages, liste d’amis revue.", "**Parlez-en** à une personne de confiance ou à l’équipe GANXÔ."),
		"Vous n’avez pas à « supporter » : se déconnecter n’est pas une défaite, c’est une protection.",
		"Conservez les preuves dans un endroit sûr (par exemple un dossier envoyé à une adresse que l’agresseur ne connaît pas)."),
	array("Cyberharcèlement et pollupostage", "harcelement", "all", false,
		"Attaques répétées ou massives visant à vous intimider, vous insulter ou envahir votre espace numérique de messages.",
		"Le cyberharcèlement regroupe des attaques répétées en ligne. Le pollupostage (« spam » malveillant) consiste à inonder votre boîte mail, vos messageries ou vos commentaires de messages pour vous épuiser, vous faire taire ou vous discréditer, parfois à plusieurs personnes à la fois.",
		array("Des dizaines de notifications ou de messages en quelques minutes.", "Votre boîte mail ou vos commentaires saturés de contenus agressifs ou humiliants.", "Des inscriptions à votre insu à des listes ou des services.", "Des attaques qui reprennent dès que vous changez de numéro ou d’adresse."),
		array("**Ne supprimez pas tout tout de suite** : faites d’abord des captures de ce qui est visible.", "**Activez les filtres** : messages d’inconnus en dossier séparé, mots masqués, commentaires limités.", "**Signalez en masse** aux plateformes en précisant qu’il s’agit d’une attaque coordonnée.", "**Changez ou ajoutez une adresse** réservée à vos proches, et prévenez-les.", "**Demandez à une personne de confiance** de lire les messages à votre place pendant quelques jours."),
		"Vous pouvez mettre vos comptes en pause : l’urgence est de vous préserver, pas de répondre.",
		""),
	array("Messages non sollicités", "harcelement", "all", false,
		"Photos sexuelles, vidéos ou propos offensants envoyés sans votre accord : ce que c’est, pourquoi ce n’est jamais « normal » et comment réagir.",
		"Recevoir une photo sexuelle, une vidéo ou des propos obscènes sans les avoir demandés est une forme de violence sexuelle en ligne. Elle peut venir d’un inconnu comme d’une personne que vous connaissez. L’absence de réponse de votre part n’est jamais un accord.",
		array("Des images ou des vidéos explicites qui arrivent sans prévenir.", "Des commentaires sexuels répétés sur votre corps ou votre apparence.", "Une insistance après un refus : « allez, juste un peu ».", "Un malaise, de la honte ou de la peur après ces messages."),
		array("**Ne transférez pas** l’image : elle peut devenir une preuve, mais pas un contenu à diffuser.", "**Faites une capture** du message avec le nom du compte, puis bloquez.", "**Signalez** le contenu sur la plateforme (« contenu sexuel non sollicité »).", "**Limitez qui peut vous écrire** : demandes de messages, comptes privés.", "**Parlez-en** : si vous êtes mineur·e, adressez-vous à un adulte de confiance sans attendre."),
		"Le refus se respecte dès le premier « non ». La honte appartient à celui ou celle qui a envoyé le message.",
		"Si la personne est mineure ou si l’image représente un·e mineur·e, ne la partagez à personne : signalez-la immédiatement aux autorités et à la plateforme."),
	array("Sextorsion", "intimite", "all", true,
		"Un chantage avec des images ou des vidéos intimes pour obtenir de l’argent, d’autres images ou de l’obéissance : réagir sans céder.",
		"La sextorsion est un chantage : une personne menace de diffuser des photos, des vidéos ou des conversations intimes pour obtenir de l’argent, d’autres contenus ou des faveurs. Elle commence souvent par une relation en ligne rapide et très flatteuse, ou par un faux profil.",
		array("Une personne que vous ne connaissez pas bien devient très proche très vite.", "On vous propose de passer en vidéo ou d’échanger des images intimes.", "Après l’envoi, un message vous menace : « paie ou je publie ».", "Les demandes augmentent, avec un compte à rebours pour vous mettre sous pression."),
		array("**Ne payez pas** et n’envoyez rien de plus : cela n’arrête généralement pas le chantage.", "**Cessez toute discussion** après avoir gardé des preuves (captures, pseudonymes, liens, numéros de paiement demandés).", "**Bloquez**, puis signalez le compte à la plateforme.", "**Sécurisez vos comptes** : mots de passe, confidentialité, liste d’amis.", "**Parlez-en vite** à une personne de confiance et créez une alerte VBG : vous serez écouté·e et orienté·e."),
		"Vous êtes victime d’une infraction, pas coupable d’avoir fait confiance. Plus tôt vous demandez de l’aide, plus il y a d’options.",
		"Si la victime est mineure, il faut avertir un adulte et les autorités sans tarder. Ne supprimez pas les preuves avant de les avoir enregistrées."),
	array("Revenge porn : photos privées partagées", "intimite", "all", false,
		"Partager ou menacer de partager des images intimes pour humilier ou se venger, notamment après une rupture : comprendre et agir.",
		"On parle de « revenge porn » (pornographie de vengeance) quand une personne publie ou menace de publier des images ou des vidéos intimes d’un·e ex-partenaire, sans son accord, pour l’humilier ou la contrôler. Même si l’image a été envoyée volontairement, la diffuser sans consentement est une violence.",
		array("Une menace de diffusion pendant ou après une rupture.", "Des images qui circulent dans des groupes ou sur des sites sans votre accord.", "Des proches ou des collègues qui vous en parlent.", "Un harcèlement associé : insultes, messages, appels."),
		array("**Documentez** : captures d’écran des publications, liens, dates, comptes concernés.", "**Demandez le retrait** : utilisez les formulaires de signalement des plateformes pour contenu intime non consenti.", "**Évitez de confronter seul·e** la personne : cela peut aggraver la situation.", "**Entourez-vous** : une personne de confiance peut vous aider à faire les démarches.", "**Créez une alerte VBG** pour être orienté·e vers un soutien psychologique et juridique."),
		"Le consentement à envoyer une image n’est pas un consentement à la diffuser.",
		"Des services spécialisés existent dans certains pays pour faire retirer des images intimes : renseignez-vous auprès d’une association ou de l’équipe GANXÔ."),
	array("Surveillance et suivi non consentis", "surveillance", "all", true,
		"Logiciels espions, accès à vos messages, suivi GPS ou surveillance sur les réseaux : reconnaître le contrôle numérique et vous en protéger.",
		"Surveiller le téléphone, les messages, la position ou l’activité en ligne d’une personne sans son accord est une forme de contrôle. Cela peut passer par un logiciel espion, l’accès à vos comptes, le partage de position imposé ou l’observation constante de vos réseaux sociaux.",
		array("Votre partenaire ou un proche sait des choses que vous ne lui avez jamais dites.", "Votre batterie se vide vite, le téléphone chauffe ou se comporte de façon étrange.", "On connaît vos déplacements en temps réel, ou on « arrive par hasard » où vous êtes.", "Vous devez justifier chaque message, chaque appel, chaque connexion."),
		array("**Ne confrontez pas** la personne avant d’être en sécurité : elle pourrait renforcer le contrôle.", "**Utilisez un appareil sûr** (celui d’une personne de confiance, un ordinateur public) pour demander de l’aide.", "**Vérifiez les accès** : applications installées, appareils connectés à vos comptes, partages de position.", "**Changez vos mots de passe** depuis un appareil sûr et activez la double authentification.", "**Parlez-en** à une association ou à l’équipe GANXÔ : un plan de sécurité peut être préparé avec vous."),
		"Si vous craignez une réaction violente, mettez votre sécurité avant la technique : retirer un espion peut alerter la personne qui l’a installé.",
		""),
	array("Traque via GPS ou réseaux sociaux", "surveillance", "all", false,
		"Être localisé·e ou rejoint·e sans l’avoir dit grâce au suivi numérique : comprendre et se protéger.",
		"La traque numérique consiste à suivre vos déplacements ou vos habitudes grâce au GPS d’un téléphone, d’une voiture, d’un traceur d’objets ou de publications sur les réseaux sociaux (photos géolocalisées, « check-in », stories).",
		array("Une personne apparaît régulièrement aux mêmes endroits que vous.", "Vous retrouvez un petit boîtier ou un traceur dans un sac ou un véhicule.", "Un compte commente chacune de vos publications, avec des détails sur vos lieux.", "Un partage de position activé que vous ne vous souvenez pas d’avoir accepté."),
		array("**Coupez le partage de position** de vos applications et de vos comptes depuis les réglages.", "**Vérifiez** votre sac, votre véhicule et vos affaires s’il y a un traceur.", "**Évitez de publier** en direct vos lieux et vos horaires.", "**Préparez un trajet différent** et prévenez une personne de confiance.", "**Signalez** la situation à l’équipe GANXÔ ou à une association."),
		"Cacher ses lieux n’est pas de la paranoïa : c’est de la prudence.",
		""),
	array("Contrôle des appareils à distance", "surveillance", "all", false,
		"Prise de contrôle de la caméra, du micro ou d’objets connectés à distance : les signes et les gestes de protection.",
		"Certains appareils (téléphone, ordinateur, caméra de maison, enceinte, télévision connectée) peuvent être pris en main à distance par une personne mal intentionnée, notamment si elle connaît vos mots de passe ou a un accès à votre réseau Wi-Fi.",
		array("La caméra ou le micro s’allume sans raison.", "Des réglages changent tout seuls : mots de passe, volume, lumières.", "Quelqu’un évoque des conversations tenues chez vous.", "Des applications inconnues apparaissent."),
		array("**Cachez ou couvrez** les caméras que vous n’utilisez pas.", "**Changez les mots de passe** du Wi-Fi et des objets connectés, depuis un appareil sûr.", "**Mettez à jour** vos appareils et supprimez les applications inconnues.", "**Retirez les accès** partagés avec des anciens proches.", "**Demandez conseil** à une personne de confiance ou à l’équipe GANXÔ."),
		"Quand vous doutez d’un appareil, ne parlez pas de vos projets devant lui.",
		""),
	array("Piratage de comptes et d’appareils", "comptes", "all", false,
		"Intrusion dans vos comptes, appareils ou données personnelles : reconnaître un piratage et reprendre le contrôle.",
		"Le piratage est l’accès non autorisé à un compte (messagerie, réseaux sociaux, banque), à un appareil ou à des données. Il peut venir d’un inconnu, mais aussi d’un proche qui connaît vos habitudes ou vos mots de passe.",
		array("Des messages envoyés depuis votre compte que vous n’avez pas écrits.", "Des alertes de connexion depuis des lieux ou appareils inconnus.", "Votre mot de passe ne fonctionne plus.", "Des achats ou des changements que vous n’avez pas faits."),
		array("**Changez immédiatement** le mot de passe depuis un appareil sûr.", "**Activez la double authentification** (code reçu par application ou SMS).", "**Déconnectez** tous les appareils et vérifiez l’adresse de récupération.", "**Prévenez vos contacts** que votre compte a été piraté.", "**Signalez** à la plateforme et, si besoin, à votre banque."),
		"Un bon mot de passe est long, unique pour chaque compte et jamais partagé.",
		""),
	array("Forcer le partage de mots de passe", "comptes", "all", false,
		"Exiger vos codes PIN, mots de passe ou accès à vos comptes : une pression qui n’a rien d’une preuve d’amour.",
		"Dans certaines relations, on vous demande vos mots de passe « pour prouver votre fidélité » ou « parce que vous n’avez rien à cacher ». Exiger vos accès est une forme de contrôle qui porte atteinte à votre vie privée et peut préparer d’autres violences.",
		array("On vous reproche de ne pas partager vos codes.", "Votre téléphone est vérifié en votre absence.", "Un refus provoque des colères, des menaces ou du chantage affectif.", "Vous changez vos habitudes pour éviter les disputes."),
		array("**Rappelez** que la confiance ne passe pas par la surveillance.", "**Gardez vos codes pour vous** : il n’y a aucune obligation de les donner.", "**Changez-les** si vous avez déjà cédé, depuis un appareil sûr.", "**Identifiez** si ce contrôle s’accompagne d’autres comportements (isolement, insultes).", "**Parlez-en** à une personne de confiance ou à l’équipe GANXÔ."),
		"Aimer quelqu’un n’oblige pas à donner accès à son intimité numérique.",
		""),
	array("Usurpation et vol d’identité", "comptes", "all", false,
		"Quand quelqu’un utilise votre nom, vos photos ou vos données pour tromper, créer des comptes ou vous nuire.",
		"L’usurpation d’identité consiste à se faire passer pour vous (faux profil, faux messages). Le vol d’identité va plus loin : vos données personnelles servent à ouvrir des comptes, souscrire des services ou obtenir des avantages à votre nom.",
		array("Des amis vous signalent un profil à votre nom ou avec vos photos.", "Des courriers, des factures ou des demandes pour des services que vous n’avez pas souscrits.", "Des messages envoyés « de votre part » qui vous mettent en difficulté.", "Des refus ou des alertes inattendus de banques ou de plateformes."),
		array("**Notez et sauvegardez** les adresses des faux profils et des preuves.", "**Signalez** l’usurpation à la plateforme (formulaire « faux compte »).", "**Prévenez vos contacts** de ne pas répondre à ce compte.", "**Surveillez** vos comptes et changez vos mots de passe.", "**Déposez plainte ou signalez** aux autorités selon la loi de votre pays si des données sensibles sont utilisées."),
		"Plus vous agissez tôt, plus il est facile de limiter les dégâts.",
		""),
	array("Doxing : informations personnelles publiées", "comptes", "all", false,
		"Publication de votre adresse, de votre numéro ou de vos photos pour vous mettre en danger : réagir et réduire l’exposition.",
		"Le doxing (ou « doxxing ») consiste à publier ou à diffuser des informations personnelles : adresse, numéro de téléphone, lieu de travail, photos, noms de proches. L’objectif est d’exposer la personne à du harcèlement ou à du danger.",
		array("Des inconnus vous contactent avec des informations très précises sur vous.", "Des visites ou des colis arrivent sans explication.", "Votre adresse ou votre numéro circule dans des groupes.", "Des proches reçoivent des messages à votre sujet."),
		array("**Faites des captures** des publications et des liens.", "**Demandez le retrait** aux plateformes (« partage d’informations privées »).", "**Prévenez** vos proches, votre employeur ou votre établissement si nécessaire.", "**Renforcez** la confidentialité de vos comptes et supprimez les anciennes publications trop précises.", "**Créez une alerte VBG** si vous craignez pour votre sécurité : un plan peut être préparé avec vous."),
		"Si vous vous sentez en danger immédiat, mettez-vous d’abord en sécurité et appelez les secours de votre pays.",
		""),
	array("Diffamation en ligne", "harcelement", "all", false,
		"Répandre des mensonges pour détruire une réputation : distinguer critique et diffamation et défendre sa réputation.",
		"La diffamation en ligne, c’est diffuser publiquement des accusations fausses ou des rumeurs pour nuire à la réputation d’une personne : accusations inventées, montages, « captures » truquées, publications répétées dans des groupes.",
		array("Une rumeur ou un mensonge à votre sujet circule sur les réseaux.", "Des captures d’écran ou des images modifiées sont partagées.", "Des proches prennent leurs distances à cause de ce qu’ils ont lu.", "Vous perdez des opportunités ou un emploi à cause de ces contenus."),
		array("**Ne répondez pas à chaud** : cela peut donner plus de visibilité.", "**Conservez** les preuves (liens, captures datées).", "**Demandez à la plateforme** de retirer le contenu faux ou injurieux.", "**Rétablissez les faits** auprès de vos proches de confiance, calmement.", "**Demandez un avis juridique** ou un accompagnement d’association."),
		"Une critique n’est pas une diffamation : c’est le caractère faux et nuisible des accusations qui compte.",
		""),
	array("Manipulation psychologique et isolement numérique", "manipulation", "all", true,
		"Contrôle des communications, blocage de contacts, isolement : reconnaître l’emprise qui passe par le numérique.",
		"La manipulation psychologique en ligne passe par le contrôle de vos communications : surveiller vos conversations, choisir vos amis, bloquer vos contacts importants, vous faire douter de vous-même (« tu exagères », « personne ne te croira »). Elle isole peu à peu pour mieux dominer.",
		array("On vous demande de supprimer ou de bloquer des proches.", "Vous êtes accusé·e en permanence de mentir ou d’exagérer.", "Vous avez peur de la réaction de l’autre quand vous êtes en ligne.", "Vos liens familiaux ou amicaux s’amenuisent."),
		array("**Gardez un lien** avec au moins une personne de confiance, même discrètement.", "**Notez** ce qui se passe (dates, messages, conséquences) dans un endroit sûr.", "**Évitez les décisions sous pression** : prenez du temps pour répondre.", "**Parlez à un professionnel** (écoute, psychologue) ou à l’équipe GANXÔ.", "**Préparez un plan de sécurité** si vous envisagez de partir ou de vous éloigner."),
		"Douter de soi est un effet de l’emprise, pas une preuve que vous avez tort.",
		"L’emprise n’est pas une question de force ou d’intelligence : toute personne peut en être victime."),
	array("Deepfakes et manipulation d’images par IA", "technologie", "all", true,
		"Fausses vidéos ou images réalistes créées pour nuire ou humilier : ce qu’il faut savoir et comment réagir.",
		"Un deepfake est une image, une vidéo ou un enregistrement audio créé ou modifié par intelligence artificielle pour faire dire ou faire quelque chose à une personne qui ne l’a jamais fait. Ces contenus peuvent être utilisés pour humilier, faire chanter ou discréditer.",
		array("Votre visage ou votre voix apparaît dans un contenu que vous n’avez jamais produit.", "Des proches vous envoient une vidéo ou un message troublant.", "Un chantage évoque une fausse image intime à votre sujet.", "Des détails anormaux : mains déformées, voix métallique, mouvements bizarres."),
		array("**Ne partagez pas** le contenu, même pour le dénoncer.", "**Faites des captures** et notez les adresses de diffusion.", "**Signalez** à la plateforme (« contenu manipulé » ou « image intime non consentie »).", "**Prévenez vos proches** que le contenu est faux.", "**Demandez de l’aide** à l’équipe GANXÔ ou à une association."),
		"Vous n’êtes pas responsable d’une image qu’une machine a fabriquée.",
		"Avant de croire ou de partager une vidéo choquante, cherchez la source d’origine : si elle est introuvable, méfiez-vous."),
	array("Sollicitation sexuelle via les technologies", "intimite", "all", false,
		"Demandes insistantes d’actes ou de conversations sexuelles en ligne : poser ses limites et se protéger.",
		"Il s’agit de demandes répétées ou pressantes d’échanges, d’images ou d’actes sexuels via la messagerie, les réseaux sociaux, les jeux ou les applications de rencontre, particulièrement lorsqu’elles visent des personnes jeunes ou vulnérables.",
		array("Une personne plus âgée ou inconnue oriente vite la conversation vers le sexe.", "On vous demande de garder le secret ou de changer de plateforme.", "Des cadeaux, de l’argent ou des promesses sont proposés en échange.", "Un refus déclenche des reproches ou des menaces."),
		array("**Dites non** clairement, ou ne répondez pas : vous n’avez pas à vous justifier.", "**Conservez** les messages et captures avant de bloquer.", "**Bloquez et signalez** le compte.", "**Parlez-en** à un adulte ou à une personne de confiance.", "**Créez une alerte VBG** si vous avez peur ou si cela se répète."),
		"Si la personne concernée est mineure, informez immédiatement un adulte de confiance et les autorités.",
		""),
	array("Arnaques sentimentales et financières", "manipulation", "all", false,
		"Faux profils qui jouent sur les émotions pour obtenir de l’argent ou des informations : repérer les signaux d’alerte.",
		"Une arnaque sentimentale commence par une relation en ligne qui semble idéale : attention constante, déclarations d’amour rapides, histoires touchantes. Puis arrivent les demandes d’argent, d’informations ou de photos. L’émotion sert à vous empêcher de réfléchir.",
		array("Le profil est très flatteur, les photos semblent « trop parfaites ».", "La personne refuse systématiquement la vidéo ou une rencontre.", "Elle traverse une urgence qui nécessite votre aide financière.", "Elle vous demande de garder la relation secrète."),
		array("**Prenez du recul** : ne payez rien dans l’urgence.", "**Vérifiez** les photos par recherche d’image inversée et l’histoire racontée.", "**Parlez-en** à une personne de confiance avant toute décision.", "**Stoppez le contact** et bloquez le profil si le doute s’installe.", "**Signalez** le compte et, si vous avez payé, contactez votre banque ou le service concerné."),
		"Les arnaqueurs savent profiter de la solitude et de la confiance : ce n’est pas une honte d’avoir été touché·e.",
		""),
	array("Forums et groupes de haine", "haine", "all", false,
		"Diffusion de propos sexistes, humiliants ou incitant à la violence dans des groupes ou forums : savoir réagir.",
		"Certains groupes ou forums en ligne diffusent des propos sexistes, homophobes, transphobes, racistes ou humiliants, et encouragent parfois la violence ou le harcèlement de personnes ciblées. La logique de groupe peut banaliser la haine.",
		array("Des publications qui rabaissent des personnes à cause de leur genre, orientation ou origine.", "Des encouragements à harceler ou à « punir » quelqu’un.", "Des partages de vos photos ou de vos informations dans ces espaces.", "Un climat où la haine est présentée comme de l’humour."),
		array("**Ne rejoignez pas** la discussion pour vous défendre : vous risqueriez d’être ciblé·e.", "**Documentez** (captures, liens) si vous êtes visé·e.", "**Signalez** le groupe ou la publication à la plateforme.", "**Soutenez** les personnes visées : un message privé peut compter énormément.", "**Parlez-en** à l’équipe GANXÔ ou à une association."),
		"Se retirer d’un espace toxique est un choix de santé, pas un abandon.",
		""),
	array("Trolling et raids numériques", "haine", "all", false,
		"Attaques coordonnées par un groupe pour harceler massivement une personne : comprendre et protéger sa santé.",
		"Un raid numérique est une attaque organisée : un groupe se coordonne pour inonder une personne de messages, de commentaires et de moqueries, souvent après un appel lancé dans un forum ou une conversation privée. Le trolling consiste à provoquer pour déstabiliser.",
		array("Une vague soudaine de commentaires de comptes inconnus.", "Des messages similaires, parfois avec le même vocabulaire ou les mêmes images.", "Votre publication est relayée par des comptes qui vous attaquent.", "Une impression d’être submergé·e, sans pause."),
		array("**Mettez vos comptes en pause** : limitez les commentaires et fermez les messages.", "**Ne répondez pas** : les raids se nourrissent de la réaction.", "**Faites des captures** avant de bloquer.", "**Prévenez vos proches** et faites-vous aider à surveiller les comptes.", "**Parlez de votre ressenti** à une personne de confiance ou à un professionnel."),
		"Un raid dure rarement longtemps : le plus important est de ne pas rester seul·e face aux messages.",
		""),
	array("Refus de services et accès bloqués", "surveillance", "all", false,
		"Couper votre accès à des comptes, outils ou services essentiels lors d’un conflit : reconnaître cette forme de contrôle.",
		"Dans un conflit ou une relation de contrôle, certaines personnes bloquent l’accès à des comptes, à des outils ou à des services essentiels : téléphone, internet, comptes bancaires, messageries, documents numériques. Priver quelqu’un de ces ressources est une façon de l’isoler ou de le contraindre.",
		array("Votre forfait ou votre connexion est coupé après une dispute.", "On vous retire l’accès à vos comptes ou à des documents partagés.", "Vous ne pouvez plus appeler vos proches sans autorisation.", "On vous dit que vous ne pouvez rien faire sans « l’accord » de l’autre."),
		array("**Notez** les coupures et leur date.", "**Créez une adresse et un compte** à vous, hors de portée de la personne.", "**Gardez des copies** de vos documents importants dans un endroit sûr.", "**Parlez-en** à un proche de confiance pour garder un lien.", "**Contactez l’équipe GANXÔ** qui peut vous orienter vers un soutien adapté."),
		"Garder un moyen de communication à soi est un geste de sécurité essentiel.",
		""),
	array("Intelligence artificielle et usages malveillants", "technologie", "all", false,
		"Fausses images, automatisation du harcèlement, robots de conversation trompeurs : comprendre les risques de l’IA et s’en protéger.",
		"L’intelligence artificielle peut être détournée : créer de fausses images ou vidéos (deepfakes), imiter une voix, automatiser des messages de harcèlement à grande échelle ou manipuler des victimes avec des robots de conversation qui se font passer pour des personnes réelles.",
		array("Un « ami » en ligne répond très vite à toute heure, avec un discours trop parfait.", "Des messages très ressemblants arrivent de nombreux comptes différents.", "Une image ou une voix vous semble réaliste mais l’origine est introuvable.", "Votre réputation est visée par du contenu fabriqué."),
		array("**Vérifiez la source** avant de croire ou de partager un contenu surprenant.", "**Ne donnez pas** d’informations sensibles à un interlocuteur que vous n’avez jamais vu ni entendu.", "**Limitez** les photos et enregistrements publics de votre visage ou de votre voix.", "**Signalez** les contenus manipulés et les comptes automatisés.", "**Parlez-en** à une personne de confiance : le doute est un bon réflexe."),
		"L’IA n’est ni bonne ni mauvaise en soi : ce sont certains usages qui sont dangereux. Un doute suffit pour prendre du recul.",
		""),
);

$now = time();
$n_fiches = 0;
foreach ($fiches as $f) {
	list($title, $cat, $aud, $pin, $sum, $def, $signs, $acts, $tip, $extra) = $f;
	$body = edu_body($def, $signs, $acts, $tip, $extra);
	$slug = cl_edu_slugify($title);
	$row = array("title" => $title, "category" => $cat, "audience" => $aud, "summary" => $sum, "body" => $body, "status" => "published", "pinned" => $pin ? 1 : 0, "updated_at" => $now);
	$exists = $db->rawQueryOne("SELECT id FROM `" . T_EDU_ITEMS . "` WHERE slug = ?", array($slug));
	if ($exists) { $db->where("id", (int) $exists["id"])->update(T_EDU_ITEMS, $row); }
	else { $row["slug"] = $slug; $row["created_at"] = $now; $db->insert(T_EDU_ITEMS, $row); }
	$n_fiches++;
}

/* ------------------------------------------------------------------ ILLUSTRATIONS (SVG provisoires) */

$bd_dir = $root . "/themes/default/statics/ganho/img/bd";
if (!is_dir($bd_dir)) { mkdir($bd_dir, 0755, true); }

function svg_person($x, $y, $skin, $shirt, $hair, $mood, $flip = 1, $s = 1.0) {
	$f = $flip < 0 ? "scale(-1,1)" : "";
	$mouth = $mood == "sad" ? '<path d="M-14 38 Q0 28 14 38" stroke="#17132B" stroke-width="4" fill="none" stroke-linecap="round"/>'
		: ($mood == "happy" ? '<path d="M-14 32 Q0 46 14 32" stroke="#17132B" stroke-width="4" fill="none" stroke-linecap="round"/>'
		: '<path d="M-12 36 L12 36" stroke="#17132B" stroke-width="4" fill="none" stroke-linecap="round"/>');
	$brow = $mood == "worry" || $mood == "sad" ? '<path d="M-26 8 L-10 14 M26 8 L10 14" stroke="#17132B" stroke-width="3.5" stroke-linecap="round"/>' : "";
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ') ' . $f . '">'
		. '<rect x="-70" y="90" width="140" height="190" rx="60" fill="' . $shirt . '"/>'
		. '<rect x="-14" y="70" width="28" height="32" rx="12" fill="' . $skin . '"/>'
		. '<circle cx="0" cy="20" r="58" fill="' . $skin . '"/>'
		. '<path d="M-60 12 Q-52 -50 4 -52 Q58 -50 60 14 Q30 -14 -60 12Z" fill="' . $hair . '"/>'
		. '<circle cx="-20" cy="22" r="5.5" fill="#17132B"/><circle cx="20" cy="22" r="5.5" fill="#17132B"/>' . $brow . $mouth
		. '</g>';
}
function svg_phone($x, $y, $rot, $content, $color = "#0B0B14") {
	return '<g transform="translate(' . $x . ',' . $y . ') rotate(' . $rot . ')"><rect x="-58" y="-100" width="116" height="200" rx="18" fill="' . $color . '"/>'
		. '<rect x="-48" y="-84" width="96" height="164" rx="8" fill="#F8F1FF"/>' . $content . '</g>';
}
function svg_bubble($x, $y, $w, $c, $tail = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ')"><rect x="0" y="0" width="' . $w . '" height="26" rx="13" fill="' . $c . '"/><path d="M' . ($tail > 0 ? 14 : $w - 14) . ' 24 l' . ($tail > 0 ? -6 : 6) . ' 10 l14 -10z" fill="' . $c . '"/></g>';
}
function svg_bubbles_stack($c1, $c2, $n = 4) {
	$o = ""; for ($i = 0; $i < $n; $i++) { $o .= svg_bubble(-40 + ($i % 2) * 10, -70 + $i * 36, 60 + ($i % 2) * 14, $i % 2 ? $c2 : $c1, $i % 2 ? -1 : 1); } return $o;
}
function svg_icon_warning($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><path d="M0 -48 L48 36 H-48Z" fill="#FFBE05" stroke="#0B0B14" stroke-width="6" stroke-linejoin="round"/><rect x="-5" y="-16" width="10" height="32" rx="5" fill="#0B0B14"/><circle cx="0" cy="26" r="6" fill="#0B0B14"/></g>';
}
function svg_icon_lock($x, $y, $s = 1, $c = "#2B33C9") {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><path d="M-26 -10 V-30 a26 26 0 0 1 52 0 V-10" stroke="' . $c . '" stroke-width="10" fill="none"/><rect x="-40" y="-12" width="80" height="62" rx="14" fill="' . $c . '"/><circle cx="0" cy="16" r="8" fill="#fff"/><rect x="-3.5" y="18" width="7" height="18" rx="3" fill="#fff"/></g>';
}
function svg_icon_shield($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><path d="M0 -60 L52 -40 V10 Q52 46 0 66 Q-52 46 -52 10 V-40Z" fill="#17703A"/><path d="M-22 4 L-6 22 L26 -16" stroke="#fff" stroke-width="10" fill="none" stroke-linecap="round" stroke-linejoin="round"/></g>';
}
function svg_icon_eye($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><path d="M-70 0 Q0 -60 70 0 Q0 60 -70 0Z" fill="#fff" stroke="#0B0B14" stroke-width="6"/><circle cx="0" cy="0" r="22" fill="#C8200E"/><circle cx="0" cy="0" r="9" fill="#0B0B14"/></g>';
}
function svg_icon_heart($x, $y, $s = 1, $c = "#E8381A") {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><path d="M0 40 C-60 -4 -34 -46 0 -18 C34 -46 60 -4 0 40Z" fill="' . $c . '"/></g>';
}
function svg_icon_coin($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><circle r="40" fill="#FFBE05" stroke="#0B0B14" stroke-width="6"/><circle r="26" fill="none" stroke="#0B0B14" stroke-width="5"/><rect x="-4" y="-14" width="8" height="28" rx="4" fill="#0B0B14"/></g>';
}
function svg_icon_question($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><circle r="44" fill="#0A66E8"/><path d="M-16 -12 a16 16 0 1 1 24 14 q-8 6 -8 14" stroke="#fff" stroke-width="9" fill="none" stroke-linecap="round"/><circle cx="0" cy="34" r="6" fill="#fff"/></g>';
}
function svg_icon_camera($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><rect x="-50" y="-30" width="100" height="70" rx="14" fill="#0B0B14"/><rect x="-24" y="-44" width="48" height="20" rx="6" fill="#0B0B14"/><circle cx="0" cy="6" r="22" fill="#fff"/><circle cx="0" cy="6" r="12" fill="#0A66E8"/></g>';
}
function svg_icon_block($x, $y, $s = 1) {
	return '<g transform="translate(' . $x . ',' . $y . ') scale(' . $s . ')"><circle r="40" fill="none" stroke="#C8200E" stroke-width="10"/><path d="M-28 -28 L28 28" stroke="#C8200E" stroke-width="10" stroke-linecap="round"/></g>';
}
function svg_frame($bg1, $bg2, $inner) {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" role="img" focusable="false"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $bg1 . '"/><stop offset="1" stop-color="' . $bg2 . '"/></linearGradient></defs>'
		. '<rect width="800" height="600" fill="url(#g)"/><rect y="540" width="800" height="60" fill="rgba(11,11,20,.18)"/>' . $inner
		. '<rect x="0" y="592" width="800" height="8" fill="#2B33C9"/></svg>';
}

$skinA = "#B9774E"; $skinB = "#8A5232"; $skinC = "#D9A06B";
$comics = array(
	array("Quand les messages ne s’arrêtent plus", "harcelement-en-ligne-quand-les-messages-ne-s-arretent-plus", "harcelement",
		"Amina reçoit des messages d’insultes sans arrêt. Cinq cases pour voir comment garder des preuves, bloquer, signaler et demander du soutien.",
		array(
			array("Les notifications s’enchaînent : insultes, moqueries, menaces.", "« Encore un message… »", "Le téléphone d’Amina n’arrête plus de vibrer. Les messages viennent de comptes différents.",
				svg_frame("#5B2BB8", "#2B33C9", svg_person(250, 260, $skinA, "#F66100", "#0B0B14", "worry") . svg_phone(560, 330, -8, svg_bubbles_stack("#C8200E", "#F66100")) . svg_icon_warning(640, 110, .9))),
			array("Amina se sent fatiguée et inquiète.", "« Je n’ose plus regarder mon téléphone. »", "Elle dort mal et évite d’ouvrir ses messages. Ce n’est pas de la faiblesse : c’est le contrecoup du harcèlement.",
				svg_frame("#0B0B14", "#2B2745", svg_person(400, 250, $skinA, "#6B6683", "#0B0B14", "sad") . '<circle cx="640" cy="120" r="46" fill="#FFBE05" opacity=".5"/>')),
			array("Elle fait des captures d’écran avec la date et le nom des comptes.", "« Je garde tout, au cas où. »", "Avant de bloquer, Amina enregistre les preuves : captures, liens, dates. Elle les range dans un endroit sûr.",
				svg_frame("#0A66E8", "#0F8B6D", svg_person(230, 260, $skinA, "#2B33C9", "#0B0B14", "neutral") . svg_phone(560, 320, 6, svg_bubbles_stack("#C8200E", "#F66100", 3)) . svg_icon_camera(640, 120, .9))),
			array("Elle bloque les comptes et les signale à la plateforme.", "« Bloquer, signaler… et respirer. »", "Elle utilise les outils de la plateforme : blocage, signalement, filtre des messages.",
				svg_frame("#0F8B6D", "#3F9A1A", svg_person(250, 260, $skinA, "#2B33C9", "#0B0B14", "neutral") . svg_phone(560, 330, 0, svg_icon_block(0, 0, 1.1)))),
			array("Amina en parle à son amie Esther et à l’équipe GANXÔ.", "Esther : « Je suis là. Tu n’as rien fait de mal. »", "Parler change tout. Esther l’écoute, et Amina crée une alerte pour être soutenue et orientée.",
				svg_frame("#5B2BB8", "#E8381A", svg_person(220, 260, $skinA, "#2B33C9", "#0B0B14", "happy") . svg_person(580, 260, $skinB, "#17703A", "#17132B", "happy", -1) . svg_icon_heart(400, 110, 1.1) . svg_icon_shield(400, 340, .8))),
		)),
	array("Une photo, un chantage", "une-photo-un-chantage-sextorsion", "intimite",
		"Karim est menacé après avoir envoyé une photo. Cinq cases pour comprendre pourquoi il ne faut pas payer et comment demander de l’aide.",
		array(
			array("Une nouvelle personne devient très proche, très vite.", "« Tu es unique. Fais-moi confiance… »", "Karim discute avec un profil très flatteur. Tout va vite : compliments, secrets, demandes d’images.",
				svg_frame("#E8381A", "#5B2BB8", svg_person(250, 260, $skinB, "#0A66E8", "#17132B", "happy") . svg_phone(560, 330, 8, svg_icon_heart(0, -20, .8) . svg_bubble(-36, 30, 64, "#F66100")))),
			array("Il envoie une photo intime, puis regrette aussitôt.", "« Je n’aurais pas dû… »", "Après l’envoi, un malaise s’installe. Karim a eu confiance : ce n’est pas une faute.",
				svg_frame("#2B33C9", "#0B0B14", svg_person(260, 260, $skinB, "#0A66E8", "#17132B", "sad") . svg_phone(570, 320, -6, svg_icon_camera(0, -10, .8)))),
			array("Le message arrive : « Paie ou je publie. »", "« Paie, ou tout le monde la verra. »", "Le chantage commence. La peur et la honte poussent à obéir, mais payer ne garantit jamais que cela s’arrête.",
				svg_frame("#C8200E", "#0B0B14", svg_person(240, 260, $skinB, "#0A66E8", "#17132B", "worry") . svg_phone(570, 320, 0, svg_bubbles_stack("#C8200E", "#F66100", 3)) . svg_icon_coin(660, 110, .9))),
			array("Karim ne paie pas, garde des preuves et bloque.", "« Je ne cède pas. Je garde les preuves. »", "Il fait des captures du chantage, ne répond plus et bloque le compte. Il signale le profil à la plateforme.",
				svg_frame("#0F8B6D", "#0A66E8", svg_person(240, 260, $skinB, "#2B33C9", "#17132B", "neutral") . svg_phone(570, 330, 0, svg_icon_block(0, 0, 1.1)) . svg_icon_camera(660, 110, .8))),
			array("Il en parle à une personne de confiance et crée une alerte.", "Sa cousine : « On va gérer ça ensemble. »", "Karim n’est pas seul. Il est écouté, rassuré et orienté vers les bonnes démarches.",
				svg_frame("#17703A", "#0F8B6D", svg_person(220, 260, $skinB, "#2B33C9", "#17132B", "happy") . svg_person(580, 260, $skinC, "#C2185B", "#17132B", "happy", -1) . svg_icon_shield(400, 130, 1))),
		)),
	array("Qui regarde par-dessus mon épaule ?", "qui-regarde-par-dessus-mon-epaule-surveillance", "surveillance",
		"Lina remarque que son partenaire sait tout ce qu’elle fait. Cinq cases sur la surveillance numérique et la façon de reprendre le contrôle.",
		array(
			array("Il sait toujours où elle est, ce qu’elle dit, avec qui.", "« Comment tu sais que j’étais là ? »", "Lina s’étonne : son partenaire connaît des détails qu’elle ne lui a jamais donnés.",
				svg_frame("#0B0B14", "#2B33C9", svg_person(250, 260, $skinC, "#C2185B", "#17132B", "worry") . svg_icon_eye(580, 220, 1.2))),
			array("Son téléphone chauffe, la batterie se vide.", "« Pourquoi il se comporte bizarrement ? »", "Elle remarque des signes étranges. Un logiciel espion pourrait être installé, ou ses comptes pourraient être partagés.",
				svg_frame("#5B2BB8", "#0B0B14", svg_person(230, 260, $skinC, "#C2185B", "#17132B", "worry") . svg_phone(570, 320, -6, svg_icon_warning(0, 0, .8)))),
			array("Elle n’en parle pas à son partenaire, mais à une amie, depuis un autre téléphone.", "« Je préfère que tu m’aides depuis ton téléphone. »", "Lina choisit un appareil sûr pour demander de l’aide, afin de ne pas être surveillée.",
				svg_frame("#0A66E8", "#5B2BB8", svg_person(220, 260, $skinC, "#C2185B", "#17132B", "neutral") . svg_person(580, 260, $skinA, "#17703A", "#0B0B14", "neutral", -1) . svg_phone(400, 300, 0, svg_icon_lock(0, 0, .9, "#17703A"), "#17703A"))),
			array("Elles vérifient les applications, les accès et les partages de position.", "« Voyons ce qui est installé. »", "Avec son amie, Lina passe en revue ses applications, coupe le partage de position et prépare le changement des mots de passe.",
				svg_frame("#0F8B6D", "#0A66E8", svg_person(230, 260, $skinC, "#C2185B", "#17132B", "neutral") . svg_phone(570, 330, 4, svg_icon_lock(0, 0, 1, "#2B33C9")))),
			array("Lina prépare un plan de sécurité avec l’équipe GANXÔ.", "« Je veux faire ça en sécurité. »", "Reprendre le contrôle demande de la prudence. L’équipe GANXÔ peut l’aider à préparer les étapes sans la mettre en danger.",
				svg_frame("#17703A", "#2B33C9", svg_person(250, 260, $skinC, "#C2185B", "#17132B", "happy") . svg_icon_shield(560, 250, 1.2))),
		)),
	array("Le faux profil", "le-faux-profil-arnaque-sentimentale", "manipulation",
		"Yao rencontre quelqu’un en ligne qui lui demande bientôt de l’argent. Cinq cases pour repérer les signaux d’une arnaque sentimentale.",
		array(
			array("Un profil très séduisant lui écrit.", "« Bonjour, ton sourire m’a tout de suite touché. »", "Yao est flatté : le profil est parfait, les compliments arrivent vite.",
				svg_frame("#C2185B", "#5B2BB8", svg_person(250, 260, $skinA, "#0A66E8", "#0B0B14", "happy") . svg_phone(570, 330, 6, svg_icon_heart(0, -10, .8)))),
			array("Les déclarations d’amour arrivent en quelques jours.", "« Je n’ai jamais ressenti ça. »", "La relation s’accélère. L’autre refuse toujours la vidéo, mais jure que c’est sérieux.",
				svg_frame("#E8381A", "#C2185B", svg_person(240, 260, $skinA, "#0A66E8", "#0B0B14", "happy") . svg_icon_heart(560, 200, 1.6) . svg_icon_heart(660, 330, .8, "#FFBE05"))),
			array("Puis vient la demande d’argent, en urgence.", "« J’ai un problème. Tu peux m’envoyer de l’argent ? »", "L’histoire est touchante, urgente, secrète. C’est le signal d’alerte principal.",
				svg_frame("#F66100", "#0B0B14", svg_person(250, 260, $skinA, "#0A66E8", "#0B0B14", "worry") . svg_phone(570, 320, -4, svg_bubble(-36, -30, 64, "#F66100")) . svg_icon_coin(660, 110, 1))),
			array("Yao doute et vérifie les photos et l’histoire.", "« Et si ce n’était pas vrai ? »", "Il fait une recherche d’image inversée, pose des questions, demande une vidéo. Les réponses ne tiennent pas.",
				svg_frame("#0A66E8", "#0F8B6D", svg_person(240, 260, $skinA, "#2B33C9", "#0B0B14", "neutral") . svg_icon_question(570, 220, 1.4))),
			array("Il arrête, bloque le profil et en parle à une personne de confiance.", "« Je ne paie pas. Merci de m’avoir écouté. »", "Yao n’est pas coupable d’avoir fait confiance. Il signale le profil et peut créer une alerte pour être soutenu.",
				svg_frame("#17703A", "#0F8B6D", svg_person(220, 260, $skinA, "#2B33C9", "#0B0B14", "happy") . svg_person(580, 260, $skinC, "#C2185B", "#17132B", "happy", -1) . svg_icon_shield(400, 130, 1))),
		)),
);

// Descriptions des illustrations WebP (texte alternatif), par BD ; une BD n'est « illustrée » que si ses 5 images existent.
$img_alt = array(
	"harcelement-en-ligne-quand-les-messages-ne-s-arretent-plus" => array(
		"Amina, assise sur un canapé la nuit, regarde son téléphone avec inquiétude tandis qu’une nuée de bulles de messages orange et rouges en jaillit.",
		"Amina, allongée dans son lit la nuit, les yeux fatigués, hésite à reprendre son téléphone posé face contre la table de chevet.",
		"Amina, à son bureau en plein jour, fait calmement des captures d’écran de ses messages ; un ordinateur, un carnet et un dossier sont posés devant elle.",
		"Amina tient son téléphone dont l’écran affiche un symbole d’interdiction et respire profondément, les yeux fermés.",
		"Amina et son amie Esther sont assises côte à côte sur un canapé ; Esther passe un bras autour de ses épaules, un ordinateur affiche un bouclier.",
	),
	"qui-regarde-par-dessus-mon-epaule-surveillance" => array(
		"Lina, dans son salon à la tombée de la nuit, tient son téléphone d’un air surpris et inquiet ; à droite flottent un grand œil et une épingle de localisation.",
		"Lina, assise dans sa chambre, regarde son téléphone qui chauffe, avec une batterie presque vide et un triangle d’avertissement ; une loupe discrète est visible derrière elle.",
		"Lina et son amie aux cheveux courts teints en turquoise discutent à voix basse à la table d’un café ; entre elles, un autre téléphone affiche un cadenas vert.",
		"Lina et son amie, côte à côte sur un canapé, regardent un téléphone ; un grand écran illustré montre des réglages désactivés, une position barrée et un cadenas.",
		"Lina sourit avec soulagement devant un grand bouclier vert orné d’un cœur ; un carnet avec un plan en étapes est posé sur le bureau.",
	),
	"le-faux-profil-arnaque-sentimentale" => array(
		"Yao, flatté, regarde son téléphone ; à droite flotte la carte d’un profil séduisant entourée de petits cœurs.",
		"Yao, allongé sur son lit avec son téléphone, rêveur ; des cœurs et des bulles de messages en jaillissent à toute vitesse.",
		"Yao, inquiet, regarde son téléphone ; une bulle orange d’urgence laisse échapper des pièces et un billet, avec une horloge.",
		"Yao, la main sur le menton, doute devant son ordinateur ; une grande loupe examine deux photos identiques et un point d’interrogation flotte.",
		"Yao, soulagé, parle avec un proche qui lui pose la main sur l’épaule ; un téléphone affiche un profil bloqué et un petit bouclier vert flotte entre eux.",
	),
);


/* ------------------------------------------------------------------ BULLES DE CONVERSATION (dit / pense / ressent) posées sur les illustrations */
/* Positions en % de l'image (centre de la bulle), queue pointant vers le personnage. Textes tirés des légendes et dialogues ; idempotent. */
function gnb($type, $text, $color, $x, $y, $tail = "b", $who = "", $emotion = "") {
	return array("type" => $type, "text" => $text, "color" => $color, "x" => $x, "y" => $y, "tail" => $tail, "who" => $who, "emotion" => $emotion);
}
$bulles = array(
	"harcelement-en-ligne-quand-les-messages-ne-s-arretent-plus" => array(
		array(gnb("pense", "Pourquoi autant de messages ? Qui m’écrit ça ?", "violet", 27, 14, "b", "Amina"), gnb("dit", "Tu es nulle. Personne ne t’aime.", "rouge", 74, 78, "t", "Comptes anonymes"), gnb("ressent", "J’ai peur et je ne comprends pas.", "violet", 26, 84, "t", "Amina", "peur")),
		array(gnb("pense", "Je n’ose plus regarder mon téléphone.", "orange", 27, 14, "br", "Amina"), gnb("ressent", "Je suis épuisée et inquiète. Ce n’est pas de la faiblesse.", "orange", 72, 80, "t", "Amina", "malaise")),
		array(gnb("dit", "Je garde tout, au cas où.", "vert", 24, 13, "br", "Amina"), gnb("pense", "Les dates et les noms des comptes doivent être visibles.", "jaune", 73, 14, "bl", "Amina"), gnb("ressent", "Je reprends un peu la main.", "vert", 26, 85, "t", "Amina", "confiance")),
		array(gnb("dit", "Bloquer, signaler… et respirer.", "vert", 22, 84, "t", "Amina"), gnb("ressent", "Je respire. C’est fini pour ce soir.", "vert", 76, 84, "t", "Amina", "calme")),
		array(gnb("dit", "Je suis là. Tu n’as rien fait de mal.", "vert", 76, 12, "bl", "Esther"), gnb("dit", "Merci. Je vais créer une alerte.", "vert", 24, 82, "t", "Amina"), gnb("ressent", "Je ne suis plus seule.", "vert", 25, 12, "b", "Amina", "calme")),
	),
	"une-photo-un-chantage-sextorsion" => array(
		array(gnb("dit", "Tu es unique. Fais-moi confiance…", "jaune", 72, 14, "b", "Profil inconnu"), gnb("ressent", "Je me sens flatté et spécial.", "vert", 28, 14, "b", "Karim", "confiance")),
		array(gnb("pense", "Je n’aurais pas dû…", "bleu", 28, 14, "b", "Karim"), gnb("ressent", "J’ai honte et je regrette.", "violet", 72, 16, "bl", "Karim", "honte")),
		array(gnb("dit", "Paie, ou tout le monde la verra.", "rouge", 72, 15, "b", "Message anonyme"), gnb("ressent", "J’ai peur. Je me sens piégé.", "violet", 28, 14, "b", "Karim", "peur")),
		array(gnb("dit", "Je ne cède pas. Je garde les preuves.", "vert", 28, 14, "b", "Karim"), gnb("ressent", "Je reprends le contrôle.", "vert", 72, 15, "b", "Karim", "calme")),
		array(gnb("dit", "On va gérer ça ensemble.", "vert", 73, 14, "b", "Sa cousine"), gnb("ressent", "Je ne suis plus seul.", "vert", 27, 14, "b", "Karim", "confiance")),
	),
	"qui-regarde-par-dessus-mon-epaule-surveillance" => array(
		array(gnb("dit", "Comment tu sais que j’étais là ?", "jaune", 30, 14, "b", "Lina"), gnb("pense", "Il sait tout ce que je fais. Ce n’est pas normal.", "orange", 73, 56, "l", "Lina"), gnb("ressent", "Je me sens surveillée.", "orange", 73, 86, "l", "Lina", "malaise")),
		array(gnb("pense", "Mon téléphone chauffe et la batterie se vide…", "jaune", 30, 14, "b", "Lina"), gnb("ressent", "Il y a peut-être un logiciel espion.", "jaune", 73, 86, "t", "Lina", "doute")),
		array(gnb("dit", "Je préfère que tu m’aides depuis ton téléphone.", "vert", 26, 14, "b", "Lina"), gnb("dit", "D’accord. On regarde ensemble.", "vert", 75, 14, "b", "Son amie"), gnb("ressent", "Je choisis un appareil sûr.", "jaune", 50, 85, "t", "Lina", "prudence")),
		array(gnb("dit", "Voyons ce qui est installé.", "vert", 28, 14, "b", "Lina"), gnb("pense", "Je coupe le partage de position et je change mes mots de passe.", "jaune", 73, 14, "b", "Lina"), gnb("ressent", "Je reprends du contrôle.", "vert", 73, 86, "t", "Lina", "confiance")),
		array(gnb("dit", "Je veux faire ça en sécurité.", "vert", 28, 14, "b", "Lina"), gnb("ressent", "Je ne suis plus seule.", "vert", 73, 14, "b", "Lina", "calme"), gnb("pense", "Avançons pas à pas, avec prudence.", "jaune", 73, 84, "t", "Lina")),
	),
	"le-faux-profil-arnaque-sentimentale" => array(
		array(gnb("dit", "Bonjour, ton sourire m’a tout de suite touché.", "jaune", 72, 14, "b", "Profil inconnu"), gnb("ressent", "Je suis flatté.", "vert", 28, 14, "b", "Yao", "confiance")),
		array(gnb("dit", "Je n’ai jamais ressenti ça. Je t’aime déjà.", "jaune", 72, 14, "b", "Profil inconnu"), gnb("pense", "C’est très rapide… mais c’est agréable.", "jaune", 28, 14, "b", "Yao")),
		array(gnb("dit", "J’ai un problème. Tu peux m’envoyer de l’argent ?", "orange", 72, 14, "b", "Profil inconnu"), gnb("pense", "Pourquoi tout est si urgent ?", "jaune", 28, 14, "b", "Yao"), gnb("ressent", "Quelque chose cloche.", "orange", 72, 85, "t", "Yao", "malaise")),
		array(gnb("pense", "Et si ce n’était pas vrai ?", "jaune", 28, 14, "b", "Yao"), gnb("dit", "Je vérifie ses photos et je demande une vidéo.", "vert", 72, 82, "t", "Yao")),
		array(gnb("dit", "Je ne paie pas. Merci de m’avoir écouté.", "vert", 27, 14, "b", "Yao"), gnb("dit", "Tu n’as rien fait de mal. On va t’aider.", "vert", 73, 14, "b", "Un proche"), gnb("ressent", "Je me sens soutenu.", "vert", 50, 85, "t", "Yao", "calme")),
	),
);
foreach ($bulles as $slug_b => $par_case) {
	foreach ($par_case as $i_b => $lst) {
		$r_b = cl_conv_clean_bubbles($lst, true);
		if ($r_b[0] === null) { fwrite(STDERR, "Bulles invalides ($slug_b, case " . ($i_b + 1) . ") : " . $r_b[1] . "\n"); exit(1); }
		$bulles[$slug_b][$i_b] = $r_b[0];
	}
}

$n_bd = 0;
foreach ($comics as $c) {
	list($title, $slug, $cat, $sum, $panels) = $c;
	$list = array();
	$has_webp = true;
	foreach ($panels as $i => $p) { if (!is_file($bd_dir . "/" . $slug . "-" . ($i + 1) . ".webp")) { $has_webp = false; } }
	foreach ($panels as $i => $p) {
		list($caption, $dialogue, $alt, $svg) = $p;
		$file = $slug . "-" . ($i + 1) . ".svg";
		file_put_contents($bd_dir . "/" . $file, $svg);
		if ($has_webp) {
			$file = $slug . "-" . ($i + 1) . ".webp";
			if (isset($img_alt[$slug][$i])) { $alt = $img_alt[$slug][$i]; }
		}
		$list[] = array("image" => "themes/default/statics/ganho/img/bd/" . $file, "alt" => $alt, "caption" => $caption, "dialogue" => $dialogue, "bubbles" => isset($bulles[$slug][$i]) ? $bulles[$slug][$i] : array());
	}
	$row = array("title" => $title, "category" => $cat, "summary" => $sum, "cover" => $list[0]["image"], "panels" => json_encode($list, JSON_UNESCAPED_UNICODE), "status" => "published", "pinned" => $n_bd == 0 ? 1 : 0, "updated_at" => $now);
	$exists = $db->rawQueryOne("SELECT id FROM `" . T_EDU_COMICS . "` WHERE slug = ?", array($slug));
	if ($exists) { $db->where("id", (int) $exists["id"])->update(T_EDU_COMICS, $row); }
	else { $row["slug"] = $slug; $row["created_at"] = $now; $db->insert(T_EDU_COMICS, $row); }
	$n_bd++;
}


echo "Fiches : $n_fiches · Bandes dessinées : $n_bd (WebP si les 5 cases existent, sinon SVG de repli dans img/bd/)\n";
