# 🖼️ Screenshots

Click any thumbnail to open it full-size; use the arrows or the left/right keys to browse.

<div class="screenshot-gallery">
{% for shot in site.data.screenshots %}
  <button type="button" class="screenshot-thumb" data-gallery="playergroup"
      data-full="{{ '/assets/img/screenshots/' | append: shot.file | relative_url }}"
      data-caption="{{ shot.caption_en }}">
    <img src="{{ '/assets/img/screenshots/' | append: shot.file | relative_url }}"
        alt="{{ shot.caption_en }}" loading="lazy">
  </button>
{% endfor %}
</div>
