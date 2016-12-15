{if $availableLanguages|count > 1}
	<script data-relocate="true">
		require(['Language', 'MysteryCode/Core/Language/Wysiwyg'], function(Language, Wysiwyg) {
			Language.addObject({
				'wcf.global.button.disabledI18n': '{lang}wcf.global.button.disabledI18n{/lang}'
			});

			var availableLanguages = { {implode from=$availableLanguages key=languageID item=languageName}{@$languageID}: '{$languageName}'{/implode} };
			var values = { {implode from=$i18nValues[$elementIdentifier] key=languageID item=value}'{@$languageID}': '{$value}'{/implode} };
			Wysiwyg.init('{@$elementIdentifier}', values, availableLanguages, {if $forceSelection}true{else}false{/if});
		});
	</script>
{/if}
