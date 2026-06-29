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

/* @help_topics/system.cache.html.twig */
class __TwigTemplate_755c328a00da846e1bfdea9393868a29 extends Template
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
        // line 6
        $context["performance_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            yield t("Performance", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 7
        $context["performance_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["performance_link_text"] ?? null), "system.performance_settings"));
        // line 8
        yield "<h2>";
        yield t("Goal", []);
        yield "</h2>
<p>";
        // line 9
        yield t("Clear the data in the site cache.", []);
        yield "</p>
<h2>";
        // line 10
        yield t("What is the cache?", []);
        yield "</h2>
<p>";
        // line 11
        yield t("Some of the calculations that are done when your site loads a page take a long time to run. To save time when these calculations would need to be done again, their results can be <em>cached</em> in your site\x27s database. There are internal mechanisms to <em>clear</em> cached data when the conditions or assumptions that went into the calculation have changed, but you can also clear cached data manually. When your site is misbehaving, a good first step is to clear the cache and see if the problem goes away.", []);
        yield "</p>
<h2>";
        // line 12
        yield t("Steps", []);
        yield "</h2>
<ol>
  <li>";
        // line 14
        yield t("In the <em>Manage</em> administrative menu, navigate to <em>Configuration</em> &gt; <em>Development</em> &gt; <em>@performance_link</em>.", ["@performance_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["performance_link"] ?? null)), ]);
        yield "</li>
  <li>";
        // line 15
        yield t("Click <em>Clear all caches</em>. Your site\x27s cached data will be cleared.", []);
        yield "</li>
</ol>
<h2>";
        // line 17
        yield t("Additional resources", []);
        yield "</h2>
<ul>
    <li>";
        // line 19
        yield t("<a href=\"https://www.drupal.org/docs/user_guide/en/prevent-cache.html\">Concept: Cache (Drupal User Guide)</a>", []);
        yield "</li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/system.cache.html.twig";
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
        return array (  86 => 19,  81 => 17,  76 => 15,  72 => 14,  67 => 12,  63 => 11,  59 => 10,  55 => 9,  50 => 8,  48 => 7,  43 => 6,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/system.cache.html.twig", "/var/www/html/web/core/modules/system/help_topics/system.cache.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 6, "trans" => 6];
        static $filters = ["escape" => 14];
        static $functions = ["render_var" => 7, "help_route_link" => 7];

        try {
            $this->sandbox->checkSecurity(
                [0 => "set", 1 => "trans"],
                [0 => "escape"],
                [0 => "render_var", 1 => "help_route_link"],
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
