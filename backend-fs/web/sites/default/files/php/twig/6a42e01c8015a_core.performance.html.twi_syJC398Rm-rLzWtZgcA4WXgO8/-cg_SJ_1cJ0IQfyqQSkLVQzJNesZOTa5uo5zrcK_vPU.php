<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* @help_topics/core.performance.html.twig */
class __TwigTemplate_0c1c4b08375f538128cb2f073638c277 extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 5
        yield "<h2>";
        yield t("What is site performance?", []);
        yield "</h2>
<p>";
        // line 6
        yield t("Site performance, in this context, refers to speed factors such as the page load time and the response time after a user action on a page.", []);
        yield "</p>
<h2>";
        // line 7
        yield t("What is caching?", []);
        yield "</h2>
<p>";
        // line 8
        yield t("Caching is saving already-rendered HTML output and other calculated data for later use the first time it is needed. This saves time, because the next time the same data is needed it can be quickly retrieved instead of recalculated. Automatic caching systems also include mechanisms to delete cached calculations or mark them as no longer valid when the underlying data changes. To facilitate that, cached data has a <em>lifetime</em>, which is the maximum time before the data will be deleted from the cache (forcing recalculation).", []);
        yield "</p>
<h2>";
        // line 9
        yield t("What is file aggregation?", []);
        yield "</h2>
<p>";
        // line 10
        yield t("Aggregation is when CSS and JavaScript files are merged together and compressed into a format that is much smaller than the original. This allows for faster transmission and faster rendering on the other end.", []);
        yield "</p>
<h2>";
        // line 11
        yield t("What can I do to improve my site\x27s performance?", []);
        yield "</h2>
<p>";
        // line 12
        yield t("The following core software modules and mechanisms can improve your site\x27s performance:", []);
        yield "</p>
<dl>
  <dt>";
        // line 14
        yield t("Internal Page Cache module", []);
        yield "</dt>
  <dd>";
        // line 15
        yield t("Caches pages requested by users who are not logged in (anonymous users). Do not use if your site needs to send different output to different anonymous users.", []);
        yield "</dd>
  <dt>";
        // line 16
        yield t("Internal Dynamic Page Cache module", []);
        yield "</dt>
  <dd>";
        // line 17
        yield t("Caches data for both authenticated and anonymous users, with non-cacheable data in the page converted to placeholders and calculated when the page is requested.", []);
        yield "</dd>
  <dt>";
        // line 18
        yield t("Big Pipe module", []);
        yield "</dt>
  <dd>";
        // line 19
        yield t("Changes the way pages are sent to users, so that cacheable parts are sent out first with placeholders, and the uncacheable or personalized parts of the page are streamed afterwards. This allows the browser to render the bulk of the page quickly and fill in the details later.", []);
        yield "</dd>
  <dt>";
        // line 20
        yield t("Performance page settings", []);
        yield "</dt>
  <dd>";
        // line 21
        yield t("In the <em>Manage</em> administrative menu, if you navigate to <em>Configuration</em> &gt; <em>Development</em> &gt; <em>Performance</em>, you will find a setting for the maximum cache lifetime, as well as the ability to turn on CSS and JavaScript file aggregation.", []);
        yield "</dd>
</dl>

<h2>";
        // line 24
        yield t("Additional resources", []);
        yield "</h2>
<ul>
  <li><a href=\"https://www.drupal.org/documentation/modules/internal_page_cache\">";
        // line 26
        yield t("Online documentation for the Internal Page Cache module", []);
        yield "</a></li>
  <li><a href=\"https://www.drupal.org/documentation/modules/dynamic_page_cache\">";
        // line 27
        yield t("Online documentation for the Internal Dynamic Page Cache module", []);
        yield "</a></li>
  <li><a href=\"https://www.drupal.org/documentation/modules/big_pipe\">";
        // line 28
        yield t("Online documentation for the BigPipe module", []);
        yield "</a></li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/core.performance.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  124 => 28,  120 => 27,  116 => 26,  111 => 24,  105 => 21,  101 => 20,  97 => 19,  93 => 18,  89 => 17,  85 => 16,  81 => 15,  77 => 14,  72 => 12,  68 => 11,  64 => 10,  60 => 9,  56 => 8,  52 => 7,  48 => 6,  43 => 5,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/core.performance.html.twig", "/var/www/html/web/core/modules/help/help_topics/core.performance.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["trans" => 5];
        static $filters = [];
        static $functions = [];

        try {
            $this->sandbox->checkSecurity(
                [0 => "trans"],
                [],
                [],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
