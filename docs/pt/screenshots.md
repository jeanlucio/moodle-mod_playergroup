# 🖼️ Capturas de Tela

Clique em qualquer miniatura para ampliar; use as setas ou as teclas de seta do teclado para navegar.

<div class="screenshot-gallery">
{% for shot in site.data.screenshots %}
  <button type="button" class="screenshot-thumb" data-gallery="playergroup"
      data-full="{{ '/assets/img/screenshots/' | append: shot.file | relative_url }}"
      data-caption="{{ shot.caption_pt }}">
    <img src="{{ '/assets/img/screenshots/' | append: shot.file | relative_url }}"
        alt="{{ shot.caption_pt }}" loading="lazy">
  </button>
{% endfor %}
</div>
