package wafeng

import (
	"fmt"
	"strings"

	corazaconf "github.com/corazawaf/coraza-coreruleset/coraza/v3"
	crs "github.com/corazawaf/coraza-coreruleset/crs/v4"
	coraza "github.com/corazawaf/coraza/v3"
	"github.com/corazawaf/coraza/v3/types"
	"github.com/jcchavezs/mergefs"

	"github.com/haku0451/ward/ward-agent/internal/config"
)

func New(cfg config.WAF, onMatch func(types.MatchedRule)) (coraza.WAF, error) {
	engine := "DetectionOnly"
	if strings.EqualFold(cfg.Mode, "blocking") {
		engine = "On"
	}

	timeoutMS := int(cfg.RequestBodyTimeout.Milliseconds())
	if timeoutMS < 1 {
		timeoutMS = 10000
	}

	directives := fmt.Sprintf(`
Include @coraza.conf-recommended
SecRuleEngine %s
SecRequestBodyAccess On
SecRequestBodyLimit %d
SecRequestBodyLimitAction Reject
SecRequestBodyNoFilesLimit %d
SecResponseBodyAccess Off
Include @crs-setup.conf.example
Include @owasp_crs/*.conf
`, engine, cfg.RequestBodyLimit, cfg.RequestBodyLimit)

	wafCfg := coraza.NewWAFConfig().
		WithRootFS(mergefs.Merge(crs.FS, corazaconf.FS)).
		WithDirectives(directives)

	if onMatch != nil {
		wafCfg = wafCfg.WithErrorCallback(onMatch)
	}

	return coraza.NewWAF(wafCfg)
}
