"""Génère themes/default/statics/css/visiteurs.purged.css (feuille allégée pour les visiteurs).
Usage, depuis forumganho/ :  python docs/build_visiteurs_css.py   (réseau requis pour npx purgecss@6)"""
import os, subprocess, tempfile, shutil, sys
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
os.chdir(root)
css = 'themes/default/statics/css/'
parts = [css + 'libs/animate.min.css', css + 'css-libs.css', css + 'master.styles.css']
tmp = 'docs/.tmp-visiteurs'   # chemins relatifs requis par PurgeCSS sous Windows
os.makedirs(tmp, exist_ok=True)
src = tmp + '/visiteurs.src.css'; out = tmp + '/out.css'
with open(src, 'w', encoding='utf8') as f:
    for p in parts:
        f.write(open(p, encoding='utf8').read().replace('\n/*# sourceMappingURL=', '\n/*'))
        f.write('\n')
env = dict(os.environ, GN_CSS_IN=src, GN_CSS_OUT=out)
subprocess.check_call('npx --yes purgecss@6 --config docs/purgecss.visiteurs.config.cjs', shell=True, env=env)
res = out
# minification niveau 1 (espaces, commentaires ; sans réordonner ni fusionner les règles)
subprocess.check_call('npx --yes clean-css-cli@5 -O1 -o ' + tmp + '/min.css ' + res, shell=True)
shutil.copy(tmp + '/min.css', css + 'visiteurs.purged.css')
print('OK', os.path.getsize(css + 'visiteurs.purged.css'), 'octets')
shutil.rmtree(tmp, ignore_errors=True)
