.. list-table::
   :header-rows: 1

   * - Type
     - RFC
     - Title
     - Status
     - Author(s)
     - PHP
{% for rfc in rfcs[category] %}
   * - {{ rfc.Type[0:1]|upper }}
     - `{{ attribute(rfc, 'PHP-RFC') }} <{{ attribute(rfc, 'PHP-RFC') }}.rst>`_
     - :raw-text:`{{ rfc.Title|default(rfc.Slug) }}`
     - {{ rfc.Status }}
     - {{ rfc.Authors|default([])|map(a => "#{a.name}")|join(', ') }}
     - {{ attribute(rfc, 'PHP Version') }}
{% endfor %}
