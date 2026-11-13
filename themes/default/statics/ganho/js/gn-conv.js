/* Bulles de conversation (Ganho) : bulles posées sur l'illustration d'une case de bande dessinée.
   - « dit »     : bulle ronde, trait plein, queue pointant vers le personnage ;
   - « pense »   : bulle nuage, trait pointillé, pastilles ;
   - « ressent » : bulle à angles coupés, éclair, pictogramme + libellé de l'émotion.
   La couleur suit un code (vert, jaune, orange, rouge, bleu, violet) mais n'est jamais seule : une icône (et pour « ressent » un libellé) la double.
   Tout texte saisi est échappé (aucun HTML n'est interprété). Utilisé par le lecteur public et par l'aperçu de l'administration. */
(function (w) {
	'use strict';

	var COLORS = {vert: 'Calme, bienveillant', jaune: 'Prudence, incertitude', orange: 'Malaise', rouge: 'Colère, danger', bleu: 'Tristesse', violet: 'Peur, honte'};
	var LABEL = {vert: 'Calme', jaune: 'Incertitude', orange: 'Malaise', rouge: 'Colère', bleu: 'Tristesse', violet: 'Peur, honte'};
	var EMO = {
		calme: ['Calme', 'vert'], confiance: ['Confiance', 'vert'], prudence: ['Prudence', 'jaune'], doute: ['Doute', 'jaune'],
		malaise: ['Malaise', 'orange'], colere: ['Colère', 'rouge'], danger: ['Danger', 'rouge'], tristesse: ['Tristesse', 'bleu'],
		peur: ['Peur', 'violet'], honte: ['Honte', 'violet']
	};
	var TAILS = {b: 'En bas', bl: 'En bas à gauche', br: 'En bas à droite', t: 'En haut', tl: 'En haut à gauche', tr: 'En haut à droite', l: 'À gauche', r: 'À droite'};
	var ICON = {
		vert: '<path d="M8 14s-6-3.7-6-8a3.5 3.5 0 0 1 6-2.4A3.5 3.5 0 0 1 14 6c0 4.3-6 8-6 8z"/>',
		jaune: '<path d="M8 1.5 15 14H1z"/><path d="M8 6v4" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/><circle cx="8" cy="12" r="1" fill="#fff"/>',
		orange: '<path d="M1 9c2-4.5 3-4.5 5 0s3 4.5 5 0 3-4 4-2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
		rouge: '<path d="M9.5 1 3 9h4l-1 6 7-9H9z"/>',
		bleu: '<path d="M8 1s5 5.5 5 9a5 5 0 0 1-10 0c0-3.5 5-9 5-9z"/>',
		violet: '<path d="M8 1l2 4.5 5 .5-3.7 3.3 1.1 5L8 11.8 3.6 14.3l1.1-5L1 6l5-.5z"/>'
	};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; });
	}
	function pct(v, d) { v = parseFloat(v); if (!isFinite(v)) { v = d; } return Math.max(5, Math.min(95, Math.round(v * 10) / 10)); }
	function pick(map, k, d) { return Object.prototype.hasOwnProperty.call(map, k) ? k : d; }

	/** Icône du code couleur (SVG en chaîne). */
	function iconSvg(color) {
		return '<svg viewBox="0 0 16 16" width="16" height="16" class="gnc-ico" aria-hidden="true" focusable="false">' + (ICON[color] || ICON.vert) + '</svg>';
	}

	function norm(b) {
		var type = pick({dit: 1, pense: 1, ressent: 1}, b && b.type, 'dit');
		var color = pick(COLORS, b && b.color, 'vert');
		return {
			type: type, color: color, text: String((b && b.text) || '').slice(0, 140),
			emotion: type === 'ressent' ? pick(EMO, b && b.emotion, '') : '',
			x: pct(b && b.x, 50), y: pct(b && b.y, 20), tail: pick(TAILS, b && b.tail, 'b'), who: String((b && b.who) || '').slice(0, 24)
		};
	}

	function emotionLabel(b) { return EMO[b.emotion] ? EMO[b.emotion][0] : LABEL[b.color]; }

	/** Texte alternatif des bulles d'une case (même logique que le PHP). */
	function altText(bubbles, n) {
		var parts = (bubbles || []).map(function (raw) {
			var b = norm(raw), who = b.who || 'Un personnage', end = /[.!?…]$/.test(b.text) ? '' : '.';
			if (b.type === 'dit') { return who + ' dit : ' + b.text + end; }
			if (b.type === 'pense') { return who + ' pense : ' + b.text + end; }
			return who + ' ressent : ' + emotionLabel(b).toLowerCase() + ' (' + b.color + '). ' + b.text + end;
		});
		return 'Case ' + n + '. ' + parts.join(' ');
	}

	/** HTML d'une bulle. edit = true : la bulle est un bouton focalisable (éditeur d'administration). */
	function bubbleHtml(raw, idx, edit, sel) {
		var b = norm(raw), head = '', ico = '';
		if (b.type === 'ressent') { head = '<span class="gnc-bub__head">' + iconSvg(b.color) + '<b>' + esc(emotionLabel(b)) + '</b></span>'; }
		else { ico = '<span class="gnc-bub__ico">' + iconSvg(b.color) + '</span>'; }
		var who = b.who ? '<span class="gnc-bub__who">' + esc(b.who) + '</span>' : '';
		var attrs = ' data-idx="' + idx + '" data-x="' + b.x + '" data-y="' + b.y + '" data-layer="' + b.type + '" data-color="' + b.color + '"';
		if (edit) { attrs += ' tabindex="0" role="button" aria-label="Bulle ' + (idx + 1) + ' (' + b.type + ', ' + esc(b.color) + ') : flèches pour déplacer, majuscule pour aller plus vite"'; }
		return '<div class="gnc-bub gnc-l-' + b.type + ' gnc-c-' + b.color + ' gnc-t-' + b.tail + (edit && sel === idx ? ' is-sel' : '') + '" style="left:' + b.x + '%;top:' + b.y + '%"' + attrs + '>' +
			'<div class="gnc-bub__in"><div class="gnc-bub__fill">' + ico + head + who + '<span class="gnc-bub__t">' + esc(b.text) + '</span></div></div><i class="gnc-tail" aria-hidden="true"></i></div>';
	}

	function overlayHtml(bubbles, edit, sel) {
		return (bubbles || []).map(function (b, i) { return bubbleHtml(b, i, edit, sel); }).join('');
	}

	/** Place les bulles d'une case : reste dans le cadre, écarte celles qui se chevauchent ; sinon repli en pile sous l'image. */
	function layout(stage) {
		var ov = stage.querySelector('.gnc-over');
		if (!ov) { return; }
		var im = stage.querySelector('img');
		var W = im ? im.clientWidth : stage.clientWidth, H = im ? im.clientHeight : stage.clientHeight;
		if (!W || !H) { return; }
		stage.classList.remove('is-stack');
		var els = [].slice.call(ov.querySelectorAll('.gnc-bub')), PAD = 14, GAP = 8;
		els.forEach(function (el) { el.style.left = el.getAttribute('data-x') + '%'; el.style.top = el.getAttribute('data-y') + '%'; });
		var it = els.map(function (el) {
			var w = el.offsetWidth, h = el.offsetHeight;
			return {el: el, w: w, h: h, cx: parseFloat(el.getAttribute('data-x')) / 100 * W, cy: parseFloat(el.getAttribute('data-y')) / 100 * H};
		});
		function clamp(o) {
			o.cx = Math.max(PAD + o.w / 2, Math.min(W - PAD - o.w / 2, o.cx));
			o.cy = Math.max(PAD + o.h / 2, Math.min(H - PAD - o.h / 2, o.cy));
		}
		function overlap(a, b) {
			return Math.abs(a.cx - b.cx) < (a.w + b.w) / 2 + GAP && Math.abs(a.cy - b.cy) < (a.h + b.h) / 2 + GAP;
		}
		it.forEach(clamp);
		for (var n = 0; n < 40; n++) {
			var moved = false;
			it.sort(function (a, b) { return a.cy - b.cy; });
			for (var i = 0; i < it.length; i++) {
				for (var j = i + 1; j < it.length; j++) {
					var a = it[i], b = it[j];
					if (!overlap(a, b)) { continue; }
					var dy = (a.cy + a.h / 2 + GAP) - (b.cy - b.h / 2);
					if (b.cy + dy + b.h / 2 <= H - PAD) { b.cy += dy; } else { a.cy -= dy; }
					clamp(a); clamp(b); moved = true;
				}
			}
			if (!moved) { break; }
		}
		var ok = true;
		for (var p = 0; p < it.length && ok; p++) { for (var q = p + 1; q < it.length; q++) { if (overlap(it[p], it[q])) { ok = false; break; } } }
		var area = 0; it.forEach(function (o) { area += o.w * o.h; });
		if (area / (W * H) > 0.3) { ok = false; }
		if (!ok) { stage.classList.add('is-stack'); els.forEach(function (el) { el.style.left = ''; el.style.top = ''; }); return; }
		it.forEach(function (o) { o.el.style.left = (o.cx / W * 100).toFixed(2) + '%'; o.el.style.top = (o.cy / H * 100).toFixed(2) + '%'; });
	}

	function legend() {
		return Object.keys(COLORS).map(function (k) { return {color: k, meaning: COLORS[k], label: LABEL[k]}; });
	}

	/** Branche les commandes du lecteur sur un conteneur .gn-conv-root. */
	function mount(root) {
		if (!root || root.__gncMounted) { return; }
		root.__gncMounted = true;
		var stages = [].slice.call(root.querySelectorAll('.gn-conv-stage'));
		stages.forEach(function (st) {
			var list; try { list = JSON.parse(st.getAttribute('data-bubbles') || '[]'); } catch (e) { list = []; }
			var ov = document.createElement('div');
			ov.className = 'gnc-over'; ov.setAttribute('aria-hidden', 'true');
			ov.innerHTML = overlayHtml(list, false);
			st.appendChild(ov);
			st.classList.add('is-ready');
		});
		function relayout() { stages.forEach(layout); }
		if (w.ResizeObserver) {
			var ro = new w.ResizeObserver(function () { relayout(); });
			stages.forEach(function (st) { ro.observe(st); });
		} else { w.addEventListener('resize', relayout); }
		stages.forEach(function (st) { var im = st.querySelector('img'); if (im && !im.complete) { im.addEventListener('load', relayout); } });
		relayout();
		[].forEach.call(root.querySelectorAll('[data-ico]'), function (el) { el.innerHTML = iconSvg(el.getAttribute('data-ico')); });

		var layers = {dit: true, pense: true, ressent: true}, reveal = false, level = 0, focus = '';
		var btns = [].slice.call(root.querySelectorAll('.gn-conv-lay'));
		var rev = root.querySelector('.gn-conv-reveal'), more = root.querySelector('.gn-conv-more');
		var cbtns = [].slice.call(root.querySelectorAll('.gn-conv-key'));
		var live = root.querySelector('.gn-conv-live');

		function apply() {
			var vis = {dit: reveal ? true : layers.dit, pense: reveal ? level >= 1 : layers.pense, ressent: reveal ? level >= 2 : layers.ressent};
			['dit', 'pense', 'ressent'].forEach(function (l) { root.classList.toggle('hide-' + l, !vis[l]); });
			btns.forEach(function (b) { var l = b.getAttribute('data-layer'); b.setAttribute('aria-pressed', vis[l] ? 'true' : 'false'); b.classList.toggle('on', vis[l]); b.disabled = reveal; });
			root.setAttribute('data-focus', focus);
			cbtns.forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-color') === focus ? 'true' : 'false'); });
			if (more) {
				more.hidden = !reveal;
				more.disabled = level >= 2;
				more.textContent = level === 0 ? 'Montrer ce qu’on pense' : (level === 1 ? 'Montrer ce qu’on ressent' : 'Tout est affiché');
			}
			if (rev) { rev.setAttribute('aria-pressed', reveal ? 'true' : 'false'); rev.classList.toggle('on', reveal); }
		}
		btns.forEach(function (b) { b.addEventListener('click', function () { var l = b.getAttribute('data-layer'); layers[l] = !layers[l]; apply(); }); });
		if (rev) { rev.addEventListener('click', function () { reveal = !reveal; level = 0; apply(); if (live) { live.textContent = reveal ? 'Mode pas à pas : seul ce qui est dit est affiché.' : 'Tout est affiché selon vos choix.'; } }); }
		if (more) { more.addEventListener('click', function () { if (level < 2) { level++; apply(); if (live) { live.textContent = level === 1 ? 'Les pensées sont affichées.' : 'Les ressentis sont affichés.'; } } }); }
		cbtns.forEach(function (b) { b.addEventListener('click', function () { var c = b.getAttribute('data-color'); focus = focus === c ? '' : c; apply(); }); });
		root.addEventListener('gn:panel', function () { if (reveal) { level = 0; apply(); } w.requestAnimationFrame(relayout); });
		apply();
	}

	w.GNConv = {overlayHtml: overlayHtml, bubbleHtml: bubbleHtml, layout: layout, alt: altText, legend: legend, iconSvg: iconSvg, mount: mount, colors: COLORS, labels: LABEL, emotions: EMO, tails: TAILS, esc: esc, norm: norm};
})(window);
