/*
 * PurgeCSS 6 : feuille allégée du forum pour les VISITEURS (non connectés).
 * Ne pas lancer directement : utiliser  python docs/build_visiteurs_css.py  (DEPUIS forumganho/ ;
 * fusionne animate + css-libs + master.styles dans un fichier temporaire, lance
 * npx --yes purgecss@6 avec cette config, écrit themes/default/statics/css/visiteurs.purged.css).
 * À relancer après toute modification de master.styles.css, css-libs.css ou
 * après l'ajout d'une classe CSS de ces feuilles dans une vue / un script.
 */
module.exports = {
  content: [
    'themes/default/apps/**/*.phtml',
    'themes/default/statics/js/*.js',
    'themes/default/statics/ganho/js/**/*.js',
    'core/**/*.php',
    'apps/**/*.php',
  ],
  css: [process.env.GN_CSS_IN],
  output: process.env.GN_CSS_OUT,
  /* rendus uniquement pour les membres connectés (pubbox, réglages d'affichage) ; les pages réservées aux membres (affiliates, wallet, settings, chat...) ne sont pas dans la safelist cl-app-* */
  blocklist: [/pubbox/, /display-settings/],
  keyframes: true, fontFace: true, variables: false,
  defaultExtractor: c => c.match(/[A-Za-z0-9_:\/\-\.%]+/g) || [],
  safelist: {
    standard: ['html', 'body', /^modal/, /^show$/, /^fade$/, /^collaps/, /^active$/, /^open$/, /^dropdown/, /^tooltip/, /^popover/, /^bs-/, /^popper/, /^is-/, /^disabled$/, /^selected$/, /^animate/, /^waitMe/, /^fancybox/, /^lozad$/, /^spa-/, /^lds-/, /^cl-app-(guest|profile|thread|search|trending|stat_pages|suggested|confirm_reg|confirm_email|events|educational|comics|alert|suggest|err404|err500)$/],
    deep: [/modal/, /dropdown/, /popover/, /tooltip/, /fancybox/, /waitMe/],
    greedy: [/^animate__/]
  }
};
