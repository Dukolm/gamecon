import { Aktivita, Obsazenost } from "../api/program";
import { TimeRange } from "../components/Timetable";
import { containsSame } from ".";


export const tagyZAktivit = (aktivity: Aktivita[]): string[] => {
  const tagyMap: Set<string> = new Set();

  for (let i = aktivity.length; i--;) {
    const { stitky } = aktivity[i];

    for (let i = stitky.length; i--;) {
      const stitek: string = stitky[i];
      tagyMap.add(stitek);
    }
  }

  return Array.from(tagyMap.keys()).sort()
}

/**
 * @param denVyber Zatím dokud nebude vyřešeno jinak
 */
export const getFiltredActivities = (activity: Aktivita[], linie: string[], tagy: string[], denVyber: string): Aktivita[] => {
  console.log(denVyber)
  return (
    tagy.length
      ? activity.filter(a => containsSame(a.stitky, tagy))
      : activity
  )
    .filter(x => containsSame([x.linie], linie))
    // .filter(x => x.cas.den === denVyber)
}

export const obsazenostZVolnoTyp = (obsazenost: Obsazenost) => {
  const { m, f, km, kf, ku } = obsazenost;
  const c = m + f;
  const kc = ku + km + kf;

  if (kc <= 0) {
    return 'u'; //aktivita bez omezení
  }
  if (c >= kc) {
    return 'x'; //beznadějně plno
  }
  if (m >= ku + km) {
    return 'f'; //muži zabrali všechna univerzální i mužská místa
  }
  if (f >= ku + kf) {
    return 'm'; //LIKE WTF? (opak předchozího)
  }
  //else
  return 'u'; //je volno a žádné pohlaví nevyžralo limit míst
}

export const casRozsahZAktivit = (aktivity: Aktivita[]): TimeRange => {
  // TODO: better way, spread operator passes arguments through stack, not optimal
  const casOd = Math.min(...aktivity.map(x => x.cas.od));
  const casDo = Math.max(...aktivity.map(x => x.cas.do));

  return { from: casOd, to: casDo };
}
