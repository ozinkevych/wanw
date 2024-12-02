
/**
 * Drupal initializer.
 * Launch as behavior and pull variables from config.
 */

  // todo: change the Editoria11y event dispatch to run later, with summarized results including post-dismissal

  // Prevent multiple inits in modules that re-trigger the document context.
var ed11yOnce;

Drupal.behaviors.editoria11y = {
  attach: function (context, settings) {

    if (context === document && !ed11yOnce && CSS.supports('selector(:is(body))')) {

      /**
       * Initiate library
       *
       * */
      // "Supports" selector drops old browsers.
      ed11yOnce = true;

      let urlParams = new URLSearchParams(window.location.search);

      let lang = drupalSettings.editoria11y.lang ? drupalSettings.editoria11y.lang : 'en';

      if (lang !== 'en') {
        lang = 'dynamic';
        ed11yLang.dynamic = ed11yLangDrupal;
      }

      let ed11yAlertMode = drupalSettings.editoria11y.assertiveness ? drupalSettings.editoria11y.assertiveness : 'polite';
      // If assertiveness is "smart" we set it to assertive if the doc was recently changed.
      const now = new Date();
      if (
        (ed11yAlertMode === 'smart' &&
          (now / 1000) - drupalSettings.editoria11y.changed < 60) ||
        urlParams.has('ed1ref')
      ) {
        ed11yAlertMode = 'assertive';
      }



      let options = {
        // todo
        // videoContent: 'youtube.com, vimeo.com, yuja.com, panopto.com',
        // audioContent: 'soundcloud.com, simplecast.com, podbean.com, buzzsprout.com, blubrry.com, transistor.fm, fusebox.fm, libsyn.com',
        // dataVizContent: 'datastudio.google.com, tableau',
        // twitterContent: 'twitter-timeline',
        lang: lang,
        checkRoots: drupalSettings.editoria11y.content_root ? drupalSettings.editoria11y.content_root : false,
        shadowComponents: drupalSettings.editoria11y.shadow_components ? drupalSettings.editoria11y.shadow_components : false,
        ignoreElements: !!drupalSettings.editoria11y.ignore_elements ? `.ck-editor *, ${drupalSettings.editoria11y.ignore_elements}` : '.ck-editor *',
        ignoreByKey: {
          'p': 'table p',
          'h': '.filter-guidelines-item *, #toolbar-administration *, nav *, .block-local-tasks-block *',
          // disable alt text tests on unspoken images
          'img': '[aria-hidden], [aria-hidden] img',
          // disable link text check on disabled links:
          'a': '[aria-hidden][tabindex], #toolbar-administration a, .filter-help > a, .contextual-region > nav a',
          // 'li': false,
          // 'blockquote': false,
          // 'iframe': false,
          // 'audio': false,
          // 'video': false,
          'table': '[role="presentation"]',
        },
        alertMode: ed11yAlertMode,
        currentPage: drupalSettings.editoria11y.page_path,
        allowHide: !!drupalSettings.editoria11y.allow_hide,
        allowOK: !!drupalSettings.editoria11y.allow_ok,
        syncedDismissals: drupalSettings.editoria11y.dismissals,
        showDismissed: urlParams.has('ed1ref'),
        ignoreAllIfAbsent : !!drupalSettings.editoria11y.ignore_all_if_absent ? drupalSettings.editoria11y.ignore_all_if_absent : false,
        // todo: ignoreAllIfPresent
        preventCheckingIfPresent: !!drupalSettings.editoria11y.no_load ? drupalSettings.editoria11y.no_load : '#quickedit-entity-toolbar, .layout-builder-form',
        // todo: preventCheckingIfAbsent
        linkStringsNewWindows: !!drupalSettings.editoria11y.link_strings_new_windows ?
          new RegExp (drupalSettings.editoria11y.link_strings_new_windows, 'g')
          : !!drupalSettings.editoria11y.ignore_link_strings ?
            new RegExp(drupalSettings.editoria11y.ignore_link_strings, 'g')
            : new RegExp ('(' + Drupal.t('download') + ')|(\\s' + Drupal.t('tab') + ')|(' + Drupal.t('window') + ')', 'g'),
        linkIgnoreStrings: !!drupalSettings.editoria11y.ignore_link_strings ? new RegExp(drupalSettings.editoria11y.ignore_link_strings, 'g') : new RegExp('(' + Drupal.t('link is external') + ')|(' + Drupal.t('link sends email') + ')', 'g'),
        linkIgnoreSelector: !!drupalSettings.editoria11y.link_ignore_selector ? drupalSettings.editoria11y.link_ignore_selector : false,
        hiddenHandlers: !!drupalSettings.editoria11y.hidden_handlers ? drupalSettings.editoria11y.hidden_handlers : '',
        theme: !!drupalSettings.editoria11y.theme ? drupalSettings.editoria11y.theme : 'sleekTheme',
        embeddedContent: !!drupalSettings.editoria11y.embedded_content_warning ? drupalSettings.editoria11y.embedded_content_warning : false,
        documentLinks: !!drupalSettings.editoria11y.download_links ? drupalSettings.editoria11y.download_links : `a[href$='.pdf'], a[href*='.pdf?'], a[href$='.doc'], a[href$='.docx'], a[href*='.doc?'], a[href*='.docx?'], a[href$='.ppt'], a[href$='.pptx'], a[href*='.ppt?'], a[href*='.pptx?'], a[href^='https://docs.google']`,
        buttonZIndex: 499,
        customTests: drupalSettings.editoria11y.custom_tests,
        cssUrls: !!drupalSettings.editoria11y.css_url ? [drupalSettings.editoria11y.css_url + '/library/css/editoria11y.css'] : false,
      };

      if (typeof editoria11yOptionsOverride !== 'undefined') {
        options = editoria11yOptions(options);
      }

      if (!!drupalSettings.editoria11y.view_reports) {
        let helpWithLink = Drupal.t("" +
          "<p>Assistive technologies and search engines rely on well-structured content. <a href='@demo'>Editoria11y</a> checks for common needs, such as image alternative text, meaningful heading outlines and well-named links. It is meant to supplement <a href='@testing'>testing the design and code</a>.<p>" +
          "<p><a href='@dashboard'>View site-wide reports <span aria-hidden='true'>&raquo;</span></a></p>" +
          "<p><a href='@github' class='ed11y-small'>Feature requests &amp; bug reports <span aria-hidden='true'>&raquo;</span></a></p>", { '@demo': 'https://itmaybejj.github.io/editoria11y/', '@testing': 'https://webaim.org/resources/evalquickref/', '@github': 'https://github.com/itmaybejj/editoria11y/issues', '@dashboard': drupalSettings.editoria11y.dashboard_url });
        ed11yLang.en.panelHelp = helpWithLink;
        if (typeof ed11yLangDrupal !== 'undefined') {
          ed11yLangDrupal.panelHelp = helpWithLink;
        }
      }

      const ed11y = new Ed11y(options);


      /**
       * Initiate sync
       *
       * */

      let csrfToken = false;
      function getCsrfToken(action, data)
      {
        {
          fetch(`${drupalSettings.editoria11y.session_url}`, {
            method: "GET"
          })
            .then(res => res.text())
            .then(token => {
              csrfToken = token;
              postData(action, data).catch(err => console.error(err));
            })
            .catch(err => console.error(err));
        }
      }

      let postData = async function (action, data) {
        if (!csrfToken) {
          getCsrfToken(action, data);
        } else {
          let apiRoot = drupalSettings.editoria11y.api_url.replace('results/report','');
          let url = `${apiRoot}${action}`;
          fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(data),
          })
            .catch((error) => console.error('Error:', error))
        }
      }

      // Purge changed aliases & deleted pages.
      if (urlParams.has('ed1ref') && urlParams.get('ed1ref') !== drupalSettings.editoria11y.page_path) {
        let data = {
          page_path: urlParams.get('ed1ref'),
        };
        window.setTimeout(function() {
          postData('purge/page', data);
        },0,data);
      }

      let results = {};
      let oks = {};
      let total = 0;
      let extractResults = function () {
        results = {};
        oks = {};
        total = 0;
        Ed11y.results.forEach(result => {
          if (result.dismissalStatus !== "ok") {
            // log all items not marked as OK
            let testName = result.test;
            testName = Ed11y.M[testName].title;
            if (results[testName]) {
              results[testName] = parseInt(results[testName]) + 1;
              total++;
            } else {
              results[testName] = 1;
              total++;
            }
          }
          if (result.dismissalStatus === "ok") {
            if (!results[result.test]) {
              oks[result.test] = Ed11y.M[result.test].title;
            }
          }
        })
      }

      let sendResults = function () {
        window.setTimeout(function () {
          total = 0;
          extractResults();
          let url = window.location.pathname + window.location.search;
          url = url.length > 1000 ? url.substring(0, 1000) : url;
          let data = {
            page_title: drupalSettings.editoria11y.page_title,
            page_path: drupalSettings.editoria11y.page_path,
            entity_id: drupalSettings.editoria11y.entity_id,
            page_count: total,
            language: drupalSettings.editoria11y.lang,
            entity_type: drupalSettings.editoria11y.entity_type, // node or false
            route_name: drupalSettings.editoria11y.route_name, // e.g., entity.node.canonical or view.frontpage.page_1
            results: results,
            oks: oks,
            page_url: url,
          };
          postData('results/report', data);
          // Short timeout to let execution queue clear.
        }, 100)
      }

      let firstRun = true;
      if (drupalSettings.editoria11y.dismissals) {
        document.addEventListener('ed11yResults', function () {
          if (firstRun) {
            sendResults();
            firstRun = false;
          }
        });
      }

      let sendDismissal = function (detail) {
        if (!!detail) {
          let data = {};
          if (detail.dismissAction === 'reset') {
            data = {
              page_path: drupalSettings.editoria11y.page_path,
              language: drupalSettings.editoria11y.lang,
              route_name: drupalSettings.editoria11y.route_name,
              dismissal_status: 'reset', // ok, ignore or reset
            };
            window.setTimeout(function() {
              sendResults();
            },100);
          } else {
            data = {
              page_title: drupalSettings.editoria11y.page_title,
              page_path: drupalSettings.editoria11y.page_path,
              entity_id: drupalSettings.editoria11y.entity_id,
              language: drupalSettings.editoria11y.lang,
              entity_type: drupalSettings.editoria11y.entity_type, // node or false
              route_name: drupalSettings.editoria11y.route_name, // e.g., entity.node.canonical or view.frontpage.page_1
              result_name: Ed11y.M[detail.dismissTest].title, // which test is sending a result
              result_key: detail.dismissTest, // which test is sending a result
              element_id: detail.dismissKey, // some recognizable attribute of the item marked
              dismissal_status: detail.dismissAction, // ok, ignore or reset
            };
            if (detail.dismissAction === 'ok') {
              window.setTimeout(function() {
                sendResults();
              },100);
            }
          }
          postData('dismiss/' + detail.dismissAction, data);
        }
      }
      if (drupalSettings.editoria11y.dismissals) {
        document.addEventListener('ed11yDismissalUpdate', function (e) {
          sendDismissal(e.detail)}, false);
      }
    }
  }
};
