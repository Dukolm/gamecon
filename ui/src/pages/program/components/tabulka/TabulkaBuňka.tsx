import { Aktivita, AktivitaPřihlášen, Obsazenost } from "../../../../api/program";
import { volnoTypZObsazenost } from "../../../../utils/tranformace";

type ObsazenostProps = { obsazenost: Obsazenost };

const ObsazenostComp = (props: ObsazenostProps) => {
  const { obsazenost } = props;

  const { m, f, km, kf, ku } = obsazenost;
  const c = m + f;
  const kc = ku + km + kf;

  if (kc !== 0)
    switch (volnoTypZObsazenost(obsazenost)) {
      case "u":
      case "x":
        return (
          <div>
            {" "}
            ({c}/{kc})
          </div>
        );
      case "f":
        return (
          <div>
            <span class="f">
              ({f}/{kf}){" "}
            </span>
            <span class="m">
              ({m}/{km + ku})
            </span>
          </div>
        );
      case "m":
        return (
          <div>
            <span class="f">
              ({f}/{kf + ku}){" "}
            </span>
            <span class="m">
              ({m}/{km})
            </span>
          </div>
        );
    }
  return <></>;
};

type TabulkaBuňkaProps = {
  aktivita: Aktivita;
  aktivitaPřihlášen: AktivitaPřihlášen;
};

export const TabulkaBuňka = (props: TabulkaBuňkaProps) => {
  const { aktivita,aktivitaPřihlášen } = props;

  const cenaVysledna = Math.round(
    aktivita.cenaZaklad * (aktivitaPřihlášen.slevaNasobic ?? 1)
  );

  const cenaVyslednaString =
  aktivitaPřihlášen.slevaNasobic === 0 || aktivita.cenaZaklad <= 0 ? (
    <>zdarma</>
  ) : (
    <>
      {(aktivitaPřihlášen.slevaNasobic ?? 1) !== 1 ? "*" : ""}
      {cenaVysledna}&thinsp;Kč
    </>
  );

  const classes: string[] = [];
  if (aktivitaPřihlášen.prihlaseno) {
    classes.push("prihlasen");
  }
  if (aktivitaPřihlášen.vedu) {
    classes.push("organizator");
  }
  // if (aktivitaPřihlášen.nahradnik) {
  //   classes.push("nahradnik");
  // }
  if (aktivita.vdalsiVlne) {
    classes.push("vDalsiVlne");
  }
  // TODO:
  // if (aktivita.) {
  //    classes.push("plno");
  // }
  if (aktivita.vBudoucnu) {
    classes.push("vBudoucnu");
  }

  return (
    <div class={classes.join(" ")}>
      <a
        class="title"
        title={aktivita.nazev}
      >
        {aktivita.nazev.substring(0, 20)}
      </a>
      <div class="obsazenost">
        <ObsazenostComp obsazenost={aktivitaPřihlášen.obsazenost} />
      </div>
      <div class="cena">{cenaVyslednaString}</div>
    </div>
  );
};
