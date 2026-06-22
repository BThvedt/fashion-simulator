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

/* @help_topics/announcements_feed.overview.html.twig */
class __TwigTemplate_cb1bb574c79888b44f64638ff0a42cb8 extends Template
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
        $context["actions_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            // line 6
            yield t("Announcements", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 8
        $context["actions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["actions_link_text"] ?? null), "announcements_feed.announcement"));
        // line 9
        $context["permissions_link_text"] = ('' === $tmp = implode('', iterator_to_array((function () use (&$context, $macros, $blocks) {
            // line 10
            yield t("View official announcements related to Drupal", []);
            yield from [];
        })(), false))) ? '' : new Markup($tmp, $this->env->getCharset());
        // line 12
        $context["permissions_link"] = $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar($this->extensions['Drupal\help\HelpTwigExtension']->getRouteLink(($context["permissions_link_text"] ?? null), "user.admin_permissions.module", ["modules" => "announcements_feed"]));
        // line 13
        yield "<h2>";
        yield t("What are Drupal announcements?", []);
        yield "</h2>
<p>";
        // line 14
        yield t("A feed of announcements about the Drupal project and Drupal Association programs.", []);
        yield "</p>
<p>";
        // line 15
        yield t("The purpose of this feed is to provide a channel for outreach directly to Drupal site owners. This content must be highly relevant to site owners interests, serve the strategic goals of the project, and/or promote the sustainability of the project and the Drupal Association.", []);
        yield "</p>
<p>";
        // line 16
        yield t("The module sources its content from a JSON feed generated from <a href=\"https://www.drupal.org/about/announcements\">here</a>. The governance policy for the content is documented <a href=\"https://www.drupal.org/node/3274085\">here</a>.", []);
        yield "</p>
<h2>";
        // line 17
        yield t("How can I see the Announcements in my site?", []);
        yield "</h2>
<p>";
        // line 18
        yield t("If you have the toolbar module enabled, you will see a direct link to them in the toolbar. If the toolbar module is not enabled, the content can always be accessed in the <em>@actions_link</em> page.", ["@actions_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["actions_link"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 19
        yield t("Who can see the Announcements?", []);
        yield "</h2>
<p>";
        // line 20
        yield t("Users with the <em>@permissions_link</em> permission can view Drupal announcements.", ["@permissions_link" => $this->env->getExtension(\Drupal\Core\Template\TwigExtension::class)->renderVar(($context["permissions_link"] ?? null)), ]);
        yield "</p>
<h2>";
        // line 21
        yield t("Additional resources", []);
        yield "</h2>
<ul>
  <li><a href=\"https://www.drupal.org/docs/core-modules-and-themes/core-modules/announcements-feed/announcements-feed-module-overview\">";
        // line 23
        yield t("Announcement module overview", []);
        yield "</a></li>
</ul>";
        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "@help_topics/announcements_feed.overview.html.twig";
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
        return array (  97 => 23,  92 => 21,  88 => 20,  84 => 19,  80 => 18,  76 => 17,  72 => 16,  68 => 15,  64 => 14,  59 => 13,  57 => 12,  53 => 10,  51 => 9,  49 => 8,  45 => 6,  43 => 5,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "@help_topics/announcements_feed.overview.html.twig", "/var/www/html/web/core/modules/announcements_feed/help_topics/announcements_feed.overview.html.twig");
    }
    
    public function ensureSecurityChecked(): void
    {
        if ($this->sandbox->isSandboxed($this->source)) {
            $this->checkSecurity();
        }
    }
    
    public function checkSecurity()
    {
        static $tags = ["set" => 5, "trans" => 6];
        static $filters = ["escape" => 18];
        static $functions = ["render_var" => 8, "help_route_link" => 8];

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
